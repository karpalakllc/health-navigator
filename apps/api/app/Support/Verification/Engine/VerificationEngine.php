<?php

namespace App\Support\Verification\Engine;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use App\Support\Licences\KomoraLicenceImporter;
use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerificationWriter;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Decides, for every doctor, facility and pharmacy profile, whether the
 * evidence from the imports verifies it — and writes the result through
 * VerificationWriter (which never overrides a staff decision).
 *
 * One run (`import:adjudicate`, after every import and nightly):
 * 1. re-matches the unattached Комора licences against today's profiles
 *    and specialty mapping (new website drafts, mapping fixes);
 * 2. evaluates every profile (DoctorRules, FacilityRules): verified under
 *    a rule, or unverified with a reason; a lapsed licence or a record gone
 *    from its source removes a verification by itself;
 * 3. raises the few genuinely uncertain cases as "uncertain" review items,
 *    grouped and prioritised (ReviewSignals), and closes those that no
 *    longer apply;
 * 4. with IMPORT_AUTO_PUBLISH_VERIFIED, publishes the drafts verified in
 *    this run; with IMPORT_AUTO_PUBLISH_FZOM_UNVERIFIED, those that became
 *    "current in ФЗОМ, no licence" (fzom_no_licence) in this run, unverified.
 *
 * A dry run decides and counts only. Every run is an import_runs row
 * (source "verification") with its counts. Drafts change status without an
 * audit-log entry each (like imports); published profiles are logged.
 */
final class VerificationEngine
{
    public const SOURCE = EvidenceLoader::ENGINE_SOURCE;

    private const CHUNK = 500;

    public function __construct(
        private readonly VerificationWriter $writer,
        private readonly VerifiedDraftPublisher $publisher,
        private readonly LicenceCandidateSource $candidates,
    ) {}

    /**
     * @throws ImportAlreadyRunning when another run is in progress
     */
    public function run(bool $dryRun = false): ImportRun
    {
        $lock = ImportAlreadyRunning::lock(self::SOURCE);

        try {
            return Doctor::withoutSyncingToSearch(fn () => Facility::withoutSyncingToSearch(fn () => $this->runLocked($dryRun)));
        } finally {
            $lock->release();
        }
    }

    private function runLocked(bool $dryRun): ImportRun
    {
        $run = ImportRun::start(self::SOURCE, $dryRun);
        $counts = [];
        $add = function (string $key, int $by = 1) use (&$counts): void {
            $counts[$key] = ($counts[$key] ?? 0) + $by;
        };

        try {
            if (! $dryRun && app()->bound(DoctorLicenceSink::class)) {
                $rematch = (new KomoraLicenceImporter($this->candidates, app(DoctorLicenceSink::class)))->rematchStaged((int) $run->getKey());

                foreach ($rematch as $key => $value) {
                    $add('licences_rematch_'.$key, $value);
                }
            }

            $loader = new EvidenceLoader;
            $signals = new ReviewSignals($loader);
            $doctorRules = new DoctorRules;
            $facilityRules = new FacilityRules;
            $newly = ['doctor' => [], 'facility' => []];
            $newlyFzomUnverified = [];
            $reindex = ['doctor' => [], 'facility' => []];

            $byNamesakes = [];

            Doctor::query()->orderBy('id')->chunkById(self::CHUNK, function ($doctors) use ($loader, $signals, $doctorRules, $dryRun, $add, &$newly, &$reindex, &$byNamesakes, &$newlyFzomUnverified): void {
                $evidence = $loader->doctors($doctors);

                foreach ($doctors as $doctor) {
                    /** @var Doctor $doctor */
                    $e = $evidence[(int) $doctor->getKey()];
                    $verdict = $doctorRules->decide($e);
                    $previousBasis = $doctor->verification_basis?->value;
                    $wasAutoVerified = $doctor->isVerified() && ! $doctor->hasStaffVerificationDecision();
                    $wasFzomUnverified = ! $doctor->isVerified() && ($doctor->verification_reasons['reason'] ?? null) === Reason::FZOM_NO_LICENCE;
                    $add($verdict->isVerified() ? 'doctors_verified.'.$verdict->rule?->value : 'doctors_unverified.'.$verdict->reason);

                    $result = $dryRun ? $this->predict($doctor, $verdict) : $this->write($doctor, $verdict, (bool) $doctor->is_published);
                    $add('doctors_result.'.$result->value);
                    $this->track('doctor', $doctor, $result, $newly, $reindex);

                    if ($verdict->reason === Reason::FZOM_NO_LICENCE && ! $wasFzomUnverified && ! $doctor->is_published
                        && $result !== VerificationResult::StaffDecisionKept) {
                        $newlyFzomUnverified[] = (int) $doctor->getKey();
                    }

                    $signals->doctor($doctor, $e, $verdict, $doctorRules, $wasAutoVerified && ! $verdict->isVerified() && (bool) $doctor->is_published, $previousBasis);

                    if (isset($verdict->evidence[0]['namesake_licences'])) {
                        $byNamesakes[] = (int) $doctor->getKey();
                    }
                }
            });

            Facility::query()->orderBy('id')->chunkById(self::CHUNK, function ($facilities) use ($loader, $signals, $facilityRules, $dryRun, $add, &$newly, &$reindex): void {
                $evidence = $loader->facilities($facilities);

                foreach ($facilities as $facility) {
                    /** @var Facility $facility */
                    $e = $evidence[(int) $facility->getKey()];
                    $verdict = $facilityRules->decide($e);
                    $previousBasis = $facility->verification_basis?->value;
                    $wasAutoVerified = $facility->isVerified() && ! $facility->hasStaffVerificationDecision();
                    $kind = $e->pharmacy ? 'pharmacies' : 'facilities';
                    $add($verdict->isVerified() ? $kind.'_verified.'.$verdict->rule?->value : $kind.'_unverified.'.$verdict->reason);

                    $result = $dryRun ? $this->predict($facility, $verdict) : $this->write($facility, $verdict, (bool) $facility->is_published);
                    $add($kind.'_result.'.$result->value);
                    $this->track('facility', $facility, $result, $newly, $reindex);

                    $signals->facility($facility, $e, $verdict, $wasAutoVerified && ! $verdict->isVerified() && (bool) $facility->is_published, $previousBasis);
                }
            });

            foreach ($signals->counts() as $reason => $value) {
                $add('review.'.$reason, $value);
            }

            if (! $dryRun) {
                $signals->raise((int) $run->getKey());
                $add('licence_items_settled_by_namesakes', $this->settleNamesakeItems($byNamesakes));
                $this->prioritiseLicenceItems();
                $this->reindex($reindex);

                if ((bool) config('import.verification.auto_publish')) {
                    $add('auto_published', $this->publisher->publishNewlyVerified($newly['doctor'], $newly['facility']));
                }

                // After the review items are raised: a draft with an open
                // item is not in the set.
                if ((bool) config('import.verification.auto_publish_fzom_unverified')) {
                    $add('auto_published_fzom_unverified', $this->publisher->publishNewlyFzomUnverified($newlyFzomUnverified));
                }
            }
        } catch (Throwable $exception) {
            ksort($counts);
            $run->forceFill(['counts' => $counts]);
            $run->fail($exception);
            report($exception);

            return $run;
        }

        ksort($counts);
        $run->finish($counts);

        return $run;
    }

    private function write(Doctor|Facility $subject, Verdict $verdict, bool $published): VerificationResult
    {
        $call = fn (): VerificationResult => $verdict->rule !== null
            ? $this->writer->verify($subject, $verdict->rule->basis(), $verdict->evidence)
            : $this->writer->unverify($subject, (string) $verdict->reason, null, $verdict->evidence);

        // Drafts are not public: no audit-log entry each (as with imports).
        return $published ? $call() : activity()->withoutLogging($call);
    }

    /**
     * What the writer would answer, without writing.
     */
    private function predict(Doctor|Facility $subject, Verdict $verdict): VerificationResult
    {
        return match (true) {
            $subject->hasStaffVerificationDecision() => VerificationResult::StaffDecisionKept,
            $verdict->rule !== null => ! $subject->isVerified() || $subject->verification_basis !== $verdict->rule->basis()
                ? VerificationResult::Verified : VerificationResult::Unchanged,
            default => $subject->isVerified() ? VerificationResult::Unverified : VerificationResult::Unchanged,
        };
    }

    /**
     * @param  'doctor'|'facility'  $type
     * @param  array{doctor: list<int>, facility: list<int>}  $newly
     * @param  array{doctor: list<int>, facility: list<int>}  $reindex
     *
     * @param-out array{doctor: list<int>, facility: list<int>} $newly
     * @param-out array{doctor: list<int>, facility: list<int>} $reindex
     */
    private function track(string $type, Doctor|Facility $subject, VerificationResult $result, array &$newly, array &$reindex): void
    {
        if ($result === VerificationResult::Verified && ! $subject->is_published) {
            $newly[$type][] = (int) $subject->getKey();
        }

        if (in_array($result, [VerificationResult::Verified, VerificationResult::Unverified], true) && $subject->is_published) {
            $reindex[$type][] = (int) $subject->getKey();
        }
    }

    /**
     * Status changes of published profiles reach the search index once,
     * after the run (the run itself does not sync record by record).
     *
     * @param  array{doctor: list<int>, facility: list<int>}  $reindex
     */
    private function reindex(array $reindex): void
    {
        foreach (array_chunk($reindex['doctor'], self::CHUNK) as $chunk) {
            Doctor::query()->whereKey($chunk)->get()->each(fn (Doctor $doctor) => $doctor->searchable());
        }

        foreach (array_chunk($reindex['facility'], self::CHUNK) as $chunk) {
            Facility::query()->whereKey($chunk)->get()->each(fn (Facility $facility) => $facility->searchable());
        }
    }

    /**
     * A Комора "ambiguous" item whose profiles were all verified through
     * their namesakes' licences (DoctorRules) has nothing left to decide for
     * the badge: dismissed, so the nightly re-match does not raise it again
     * while nothing changes (a changed candidate set reopens it).
     *
     * @param  list<int>  $doctorIds
     */
    private function settleNamesakeItems(array $doctorIds): int
    {
        if ($doctorIds === []) {
            return 0;
        }

        $verified = array_flip($doctorIds);
        $settled = 0;

        ImportReviewItem::query()->open()
            ->where('source', KomoraLicenceImporter::SOURCE)
            ->where('kind', ImportReviewKind::Unmatched)
            ->get()
            ->each(function (ImportReviewItem $item) use ($verified, &$settled): void {
                $ids = array_map('intval', (array) ($item->details['candidate_doctor_ids'] ?? []));

                if (($item->details['reason'] ?? null) === 'ambiguous' && $ids !== []
                    && array_filter($ids, fn (int $id): bool => ! isset($verified[$id])) === []
                    && $item->resolve(ImportReviewStatus::Dismissed, 'verified_without_number', null)) {
                    $settled++;
                }
            });

        return $settled;
    }

    /**
     * A licence several profiles fit (Комора "ambiguous") decides a
     * verification: ranked above the ordinary queue, higher when one of the
     * profiles is public.
     */
    private function prioritiseLicenceItems(): void
    {
        $items = ImportReviewItem::query()->open()
            ->where('source', KomoraLicenceImporter::SOURCE)
            ->where('kind', ImportReviewKind::Unmatched)
            ->get(['id', 'details', 'priority']);
        $candidateIds = $items->flatMap(fn (ImportReviewItem $item): array => array_map('intval', (array) ($item->details['candidate_doctor_ids'] ?? [])))->unique()->values()->all();
        $published = array_flip(array_map('intval', DB::table('doctors')->whereIn('id', $candidateIds === [] ? [0] : $candidateIds)->where('is_published', true)->pluck('id')->all()));

        foreach ($items as $item) {
            $ids = array_map('intval', (array) ($item->details['candidate_doctor_ids'] ?? []));
            $priority = 20 + (array_filter($ids, fn (int $id): bool => isset($published[$id])) !== [] ? 100 : 0);

            if ($item->priority !== $priority) {
                ImportReviewItem::query()->whereKey($item->getKey())->update(['priority' => $priority]);
            }
        }
    }

    /**
     * For callers that run the engine after something else (an import) and
     * must not fail because of it.
     */
    public function runQuietly(): ?ImportRun
    {
        try {
            return $this->run();
        } catch (ImportAlreadyRunning) {
            return null;
        }
    }
}
