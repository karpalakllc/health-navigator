<?php

namespace App\Support\Verification\Engine;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportReviewItem;

/**
 * Collects, during one engine run, the few cases a person must decide, and
 * raises them as "uncertain" review items afterwards — grouped wherever one
 * decision settles many profiles:
 *
 * - specialty_mapping: a specialist Комора wording and a profile wording
 *   that never fit although name and workplace do; one mapping fix in
 *   „Licence specialty mapping“ settles every doctor behind the pair;
 * - flagged_source: a website flagged as compromised or stale is the only
 *   thing between its doctors and a verification; staff trust the site (one
 *   click) or dismiss;
 * - verification_lost: a published profile lost its verification (licence
 *   expired or off the list, gone from ФЗОМ); raised again on every run
 *   while it stays unverified, so it is open until the verification returns
 *   or staff act (a dismissal sticks while nothing changes);
 * - register_mismatch: a published facility no longer matches the register.
 *
 * Priority: published profiles and big groups first.
 */
final class ReviewSignals
{
    /** @var array<string, array{komora: string, profile: string, doctor_ids: list<int>, published: int}> */
    private array $pairs = [];

    /** @var array<int, array{doctor_ids: list<int>, published: int}> */
    private array $sites = [];

    /** @var list<array{key: string, title: string, details: array<string, mixed>, subject: Doctor|Facility, priority: int}> */
    private array $single = [];

    public function __construct(private readonly EvidenceLoader $loader) {}

    public function doctor(Doctor $doctor, DoctorEvidence $e, Verdict $verdict, DoctorRules $rules, bool $lostVerification, ?string $previousBasis): void
    {
        if ($verdict->isVerified()) {
            return;
        }

        if ($lostVerification) {
            $this->single[] = [
                'key' => 'verification-lost:doctor:'.$doctor->getKey(),
                'title' => 'Lost verification: '.$doctor->full_name.' ('.str_replace('_', ' ', (string) $verdict->reason).')',
                'details' => ['reason' => 'verification_lost', 'why' => $verdict->reason, 'previous_basis' => $previousBasis, 'published' => true],
                'subject' => $doctor,
                'priority' => 1000 + min(500, $e->reviewsCount),
            ];
        }

        $identity = $e->fzomCurrent || array_filter($e->websites, fn (WebsiteFact $site): bool => $site->counts()) !== [];
        $mismatch = $e->licence !== null && ! $e->licence->fits ? $e->licence : $e->stagedMismatch;

        // A mapping question only: a specialist licence against a profile
        // specialty. A general doctor's licence against a specialist profile
        // contradicts the profile (often a doctor still in specialisation);
        // mapping it would be wrong, so it stays unverified without an item.
        if ($verdict->reason === Reason::SPECIALTY_MISMATCH && $identity && $mismatch !== null
            && $mismatch->nameAgrees && $mismatch->nameUnique && ! $mismatch->general) {
            $profile = $e->specialtyNames;
            sort($profile, SORT_STRING);
            $komora = (string) ($mismatch->specialty ?? '—');
            $key = ($mismatch->specialtyKey ?? $komora).'|'.implode('+', $profile);
            $this->pairs[$key] ??= ['komora' => $komora, 'profile' => $profile === [] ? '—' : implode(', ', $profile), 'doctor_ids' => [], 'published' => 0];
            $this->pairs[$key]['doctor_ids'][] = (int) $doctor->getKey();
            $this->pairs[$key]['published'] += $e->published ? 1 : 0;
        }

        $flagged = array_values(array_filter($e->websites, fn (WebsiteFact $site): bool => $site->linked && $site->highConfidence && $site->isFlagged()));

        if ($flagged !== [] && $rules->decide($e->withFlaggedSitesTrusted())->isVerified()) {
            foreach ($flagged as $site) {
                $this->sites[$site->facilityId] ??= ['doctor_ids' => [], 'published' => 0];
                $this->sites[$site->facilityId]['doctor_ids'][] = (int) $doctor->getKey();
                $this->sites[$site->facilityId]['published'] += $e->published ? 1 : 0;
            }
        }
    }

    public function facility(Facility $facility, FacilityEvidence $e, Verdict $verdict, bool $lostVerification, ?string $previousBasis): void
    {
        if ($verdict->isVerified()) {
            return;
        }

        if ($lostVerification) {
            $this->single[] = [
                'key' => 'verification-lost:facility:'.$facility->getKey(),
                'title' => 'Lost verification: '.$facility->name.' ('.str_replace('_', ' ', (string) $verdict->reason).')',
                'details' => ['reason' => 'verification_lost', 'why' => $verdict->reason, 'previous_basis' => $previousBasis, 'published' => true],
                'subject' => $facility,
                'priority' => 1000,
            ];
        } elseif ($verdict->reason === Reason::REGISTER_MISMATCH && $e->published) {
            $this->single[] = [
                'key' => 'register-mismatch:'.$facility->getKey(),
                'title' => 'Differs from the ФЗОМ register: '.$facility->name,
                'details' => ['reason' => 'register_mismatch'] + ($verdict->evidence[0] ?? []),
                'subject' => $facility,
                'priority' => 500,
            ];
        }
    }

    /**
     * Counts per reason of what would be (or was) raised.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['specialty_mapping' => count($this->pairs), 'flagged_source' => count($this->sites)];

        foreach ($this->single as $item) {
            $reason = (string) $item['details']['reason'];
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Raises (or refreshes) every collected item and closes the engine's
     * open items nothing raised this time.
     */
    public function raise(int $runId): void
    {
        $raised = [];

        foreach ($this->pairs as $key => $pair) {
            $count = count($pair['doctor_ids']);
            $raised[] = $this->item('specialty-pair:'.$key, sprintf('Licence specialty „%s“ never fits „%s“: %d %s', $pair['komora'], $pair['profile'], $count, $count === 1 ? 'doctor' : 'doctors'), [
                'reason' => 'specialty_mapping',
                'komora_specialty' => $pair['komora'],
                'profile_specialties' => $pair['profile'],
                'doctors' => $count,
                'published' => $pair['published'],
                'doctor_ids' => array_slice($pair['doctor_ids'], 0, 50),
                'action' => 'Map the wording in Data import → Licence specialty mapping (or dismiss: they stay unverified).',
            ], null, $runId, 10 * $count + 50 * $pair['published']);
        }

        foreach ($this->sites as $facilityId => $site) {
            $facility = Facility::query()->find($facilityId);

            if ($facility === null) {
                continue;
            }

            $count = count($site['doctor_ids']);
            $flags = $this->loader->siteFlags($facilityId);
            $raised[] = $this->item(EvidenceLoader::FLAGGED_SITE_KEY.$facilityId, sprintf('Website flagged as %s: %s — %d %s would be verified', implode(' and ', $flags), $facility->name, $count, $count === 1 ? 'doctor' : 'doctors'), [
                'reason' => 'flagged_source',
                'flags' => $flags,
                'note' => $this->loader->siteFlagNote($facilityId),
                'doctors' => $count,
                'published' => $site['published'],
                'doctor_ids' => array_slice($site['doctor_ids'], 0, 50),
                'action' => 'Trust this website if its staff list is current, or dismiss: its doctors stay unverified.',
            ], $facility, $runId, 10 * $count + 50 * $site['published']);
        }

        foreach ($this->single as $single) {
            $raised[] = $this->item($single['key'], $single['title'], $single['details'], $single['subject'], $runId, $single['priority']);
        }

        ImportReviewItem::query()->open()
            ->where('source', EvidenceLoader::ENGINE_SOURCE)
            ->where('kind', ImportReviewKind::Uncertain)
            ->whereNotIn('id', $raised === [] ? [0] : $raised)
            ->update(['status' => 'resolved', 'resolution' => 'no_longer_applies', 'resolved_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function item(string $key, string $title, array $details, Doctor|Facility|null $subject, int $runId, int $priority): int
    {
        return (int) ImportReviewItem::raise(EvidenceLoader::ENGINE_SOURCE, ImportReviewKind::Uncertain, $key, $title, $details, $subject, $runId, $priority)->getKey();
    }
}
