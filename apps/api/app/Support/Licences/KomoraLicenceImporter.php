<?php

namespace App\Support\Licences;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\KomoraLicence;
use App\Models\LicenceSpecialtyMapping;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\Contracts\LicenceAttachResult;
use App\Support\Import\Contracts\LicenceRecord;
use App\Support\Import\Contracts\LicenceReviewReason;
use App\Support\Import\NameKey;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a parsed Комора list: decides each row (KomoraLicenceMatcher),
 * hands attachments and review items to the DoctorLicenceSink, and keeps
 * the komora_licences staging current. A dry run decides and counts only —
 * nothing is written anywhere.
 *
 * A licence that is no longer on the list is marked missing_since (only
 * after a complete, cleanly parsed list, so a failed download or an
 * unreadable line never looks like a lapsed licence); a profile it was
 * attached to stops showing „Лиценца: важечка“ and is queued as missing.
 * Nothing is ever unpublished here: lapsed and expired licences are for
 * staff to review.
 * A staging row that stays off the next complete list too, attached to no
 * profile, is deleted (retention).
 */
final class KomoraLicenceImporter
{
    /** import_runs.source and the provenance source of attached licences. */
    public const SOURCE = 'komora';

    public function __construct(
        private readonly LicenceCandidateSource $candidates,
        private readonly ?DoctorLicenceSink $sink,
    ) {}

    /**
     * @param  bool  $completeList  every file of the list was read (not a partial, local run)
     * @return array<string, int>
     */
    public function import(LicenceParseResult $parsed, CarbonImmutable $listDate, bool $dryRun, bool $completeList = true, ?int $importRunId = null): array
    {
        $counts = [
            'rows_parsed' => count($parsed->rows),
            'parse_failures' => count($parsed->failures),
            'duplicate_numbers' => 0,
            'attached' => 0,
            'already_attached' => 0,
            'locked' => 0,
            'conflict' => 0,
            'doctor_not_found' => 0,
            'ambiguous' => 0,
            'no_match' => 0,
            'specialty_mismatch' => 0,
            'expired' => 0,
            'unmapped_specialties' => 0,
            'missing_from_list' => 0,
            'attached_off_list' => 0,
            'pruned_off_list' => 0,
        ];

        $rows = [];

        foreach ($parsed->rows as $row) {
            if (isset($rows[$row->licenceNumber])) {
                $counts['duplicate_numbers']++;

                continue;
            }

            $rows[$row->licenceNumber] = $row;
        }

        $rows = array_values($rows);
        $map = new LicenceSpecialtyMap;
        $today = CarbonImmutable::today();

        foreach ($rows as $row) {
            if ($row->validUntil->lt($today)) {
                $counts['expired']++;
            }
        }

        $unmapped = [];

        foreach ($rows as $row) {
            if ($row->specialty !== null && ! $map->knowsLicenceSpecialty($row->specialty)) {
                $unmapped[SpecialtyKey::for($row->specialty)] = $row->specialty;
            }
        }

        $counts['unmapped_specialties'] = count($unmapped);
        $decisions = (new KomoraLicenceMatcher($this->candidates, $map))->decide($rows);

        if ($dryRun) {
            foreach ($decisions as $decision) {
                $counts[$this->dryRunOutcome($decision)]++;
            }

            return $counts;
        }

        $sink = $this->sink ?? throw new RuntimeException('No DoctorLicenceSink is bound: the import core (W6-A) provides it. Only a dry run works without one.');
        $startedAt = now();

        DB::transaction(function () use ($sink, $decisions, $unmapped, $listDate, $importRunId, $startedAt, &$counts): void {
            foreach ($unmapped as $text) {
                $this->registerUnmapped($text);
            }

            foreach ($decisions as $decision) {
                $record = $this->record($decision->row, $listDate, $importRunId);
                $outcome = $this->apply($sink, $decision, $record);
                $counts[$this->countAs($decision, $outcome)]++;

                $this->stage($decision, $outcome, $listDate, $startedAt);
            }
        });

        if ($completeList && $parsed->failures === []) {
            // Licences attached to a profile that left the list: the profile
            // stops showing „Лиценца: важечка“ (Doctor::hasValidLicence())
            // and staff get a review item; nothing is unpublished.
            $offList = KomoraLicence::query()
                ->where('last_seen_at', '<', $startedAt)
                ->whereNull('missing_since')
                ->whereNotNull('doctor_id')
                ->get();

            $counts['missing_from_list'] = KomoraLicence::query()
                ->where('last_seen_at', '<', $startedAt)
                ->whereNull('missing_since')
                ->update(['missing_since' => $listDate->toDateString()]);

            foreach ($offList as $licence) {
                $doctor = Doctor::query()->find($licence->doctor_id);

                if ($doctor === null) {
                    continue;
                }

                $counts['attached_off_list']++;
                ImportReviewItem::raise(self::SOURCE, ImportReviewKind::Missing, 'licence-off-list:'.$licence->licence_number,
                    'Licence no longer on the Комора list: '.$doctor->full_name, [
                        'reason' => 'licence_off_list',
                        'licence_number' => $licence->licence_number,
                        'list_date' => $listDate->toDateString(),
                        'published' => (bool) $doctor->is_published,
                    ], $doctor, $importRunId);
            }

            // Retention (docs/data-inventory.md): a licence already missing
            // from the previous complete list and attached to no profile is
            // of no further use — deleted. One still attached stays as the
            // review signal until staff deal with the profile.
            $counts['pruned_off_list'] = KomoraLicence::query()
                ->whereNotNull('missing_since')
                ->where('missing_since', '<', $listDate->toDateString())
                ->whereNull('doctor_id')
                ->delete();
        }

        return $counts;
    }

    private function countAs(LicenceDecision $decision, string $outcome): string
    {
        if (in_array($outcome, ['attached', 'unchanged'], true)) {
            return $decision->alreadyAttached || $outcome === 'unchanged' ? 'already_attached' : 'attached';
        }

        return $outcome;
    }

    private function dryRunOutcome(LicenceDecision $decision): string
    {
        if ($decision->attaches()) {
            return $decision->alreadyAttached ? 'already_attached' : 'attached';
        }

        return ($decision->reason ?? LicenceReviewReason::NoMatch)->value;
    }

    private function apply(DoctorLicenceSink $sink, LicenceDecision $decision, LicenceRecord $record): string
    {
        if ($decision->doctorId !== null) {
            return match ($sink->attach($decision->doctorId, $record)) {
                LicenceAttachResult::Attached => 'attached',
                LicenceAttachResult::Unchanged => 'unchanged',
                LicenceAttachResult::Locked => 'locked',
                LicenceAttachResult::Conflict => 'conflict',
                LicenceAttachResult::DoctorNotFound => 'doctor_not_found',
            };
        }

        $reason = $decision->reason ?? LicenceReviewReason::NoMatch;
        $sink->queueForReview($record, $reason, $decision->candidateDoctorIds);

        return $reason->value;
    }

    private function record(ParsedLicenceRow $row, CarbonImmutable $listDate, ?int $importRunId): LicenceRecord
    {
        return new LicenceRecord(
            licenceNumber: $row->licenceNumber,
            validUntil: $row->validUntil,
            fullName: $row->fullName,
            specialty: $row->specialty,
            observedAt: $listDate,
            source: self::SOURCE,
            sourceReference: $row->sourceReference,
            importRunId: $importRunId,
        );
    }

    private function stage(LicenceDecision $decision, string $outcome, CarbonImmutable $listDate, Carbon $seenAt): void
    {
        $row = $decision->row;
        $licence = KomoraLicence::query()->firstOrNew(['licence_number' => $row->licenceNumber]);
        $attached = in_array($outcome, ['attached', 'unchanged'], true);

        $licence->fill([
            'full_name' => $row->fullName,
            'name_key' => NameKey::for($row->fullName),
            'specialty' => $row->specialty,
            'specialty_key' => $row->specialty === null ? null : SpecialtyKey::for($row->specialty),
            'valid_until' => $row->validUntil->toDateString(),
            'list_date' => $listDate->toDateString(),
            'source_reference' => $row->sourceReference,
            'last_seen_at' => $seenAt,
            'missing_since' => null,
            'outcome' => $outcome,
            'doctor_id' => $attached ? $decision->doctorId : null,
            'candidate_doctor_ids' => $decision->candidateDoctorIds === [] ? null : $decision->candidateDoctorIds,
            'matched_at' => $attached ? ($licence->matched_at ?? $seenAt) : null,
        ]);

        if (! $licence->exists) {
            $licence->first_seen_at = $seenAt;
        }

        $licence->save();
    }

    /**
     * Wording first seen on this list: added to the mapping, unmapped, so it
     * shows up for review in „Licence specialty mapping“.
     */
    private function registerUnmapped(string $text): void
    {
        $exists = LicenceSpecialtyMapping::query()
            ->where('source', LicenceSpecialtyMapping::SOURCE_KOMORA)
            ->where('source_key', SpecialtyKey::for($text))
            ->exists();

        if (! $exists) {
            LicenceSpecialtyMapping::query()->create([
                'source' => LicenceSpecialtyMapping::SOURCE_KOMORA,
                'source_text' => $text,
            ]);
        }
    }
}
