<?php

namespace App\Support\Verification\Engine;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\ImportSuppression;
use App\Support\Import\Fzom\FzomImporter;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;
use App\Support\Import\NameKey;
use App\Support\Import\Website\InstitutionsJsonImporter;
use App\Support\Licences\Contracts\LicenceCandidate;
use App\Support\Licences\LicenceSpecialtyMap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loads the evidence for profiles in bulk (a few queries per chunk, not per
 * profile): ФЗОМ source records and links, the Комора list staging, website
 * staff-page records, owner links and suppressions.
 *
 * "Current" in ФЗОМ means listed by the latest complete snapshot that was
 * applied successfully; a record absent from it no longer counts.
 */
final class EvidenceLoader
{
    public const ENGINE_SOURCE = 'verification';

    /** Item key prefix of a flagged website staff decide on (and may trust). */
    public const FLAGGED_SITE_KEY = 'flagged-source:';

    public const TRUSTED_RESOLUTION = 'source_trusted';

    private readonly LicenceSpecialtyMap $map;

    private readonly CarbonImmutable $today;

    private readonly ?CarbonImmutable $fzomCurrentSince;

    /** @var array<string, list<object>> sorted name key => rows of the current list */
    private array $listByName = [];

    /** @var array{doctor_ids: array<int, true>, facsimiles: array<string, true>, licences: array<string, true>} */
    private array $suppressed = ['doctor_ids' => [], 'facsimiles' => [], 'licences' => []];

    /** @var array<int, list<string>> facility id => website warnings */
    private array $siteFlags = [];

    /** @var array<int, string> facility id => the sentence behind the warning */
    private array $siteFlagNotes = [];

    /** @var array<int, true> */
    private array $trustedSites = [];

    /** @var array<int, CarbonImmutable> facility id => when its site was last imported (the institution record) */
    private array $siteSeen = [];

    /**
     * facility id => institution record key => when that research slice last
     * imported the site. Several slices can describe one facility; each one
     * only speaks for the staff-page entries it listed.
     *
     * @var array<int, array<string, CarbonImmutable>>
     */
    private array $siteSeenByRecord = [];

    /** No website evidence older than this counts (the site was not imported again). */
    private readonly CarbonImmutable $websiteSince;

    /** ФЗОМ has not been heard from within import.verification.fzom_max_age_days. */
    private bool $fzomStale = false;

    public function __construct(?LicenceSpecialtyMap $map = null)
    {
        $this->map = $map ?? new LicenceSpecialtyMap;
        $this->today = CarbonImmutable::today();
        $this->websiteSince = CarbonImmutable::now()->subDays(max(1, (int) config('import.verification.website_max_age_days', 180)));
        $this->fzomCurrentSince = $this->fzomSnapshotStart();
        $this->loadList();
        $this->loadSuppressions();
        $this->loadSites();
    }

    /**
     * The latest ФЗОМ snapshot is older than the maximum age and no later run
     * confirmed it (304): nothing from ФЗОМ counts as current.
     */
    public function fzomStale(): bool
    {
        return $this->fzomStale;
    }

    public function siteFlagNote(int $facilityId): ?string
    {
        return $this->siteFlagNotes[$facilityId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function siteFlags(int $facilityId): array
    {
        return $this->siteFlags[$facilityId] ?? [];
    }

    /**
     * @param  Collection<int, Doctor>  $doctors
     * @return array<int, DoctorEvidence>
     */
    public function doctors(Collection $doctors): array
    {
        $ids = $doctors->map(fn (Doctor $doctor): int => (int) $doctor->getKey())->values()->all();

        if ($ids === []) {
            return [];
        }

        $records = DB::table('source_records')
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
            ->whereIn('subject_id', $ids)
            ->whereIn('source', [FzomImporter::SOURCE, InstitutionsJsonImporter::SOURCE])
            ->orderBy('id')
            ->get(['id', 'source', 'external_key', 'subject_id', 'payload', 'last_seen_at'])
            ->groupBy('subject_id');
        $links = DB::table('doctor_facility')->whereIn('doctor_id', $ids)->get(['doctor_id', 'facility_id', 'source'])->groupBy('doctor_id');
        $specialties = DB::table('doctor_specialty')
            ->join('specialties', 'specialties.id', '=', 'doctor_specialty.specialty_id')
            ->whereIn('doctor_specialty.doctor_id', $ids)
            ->whereNull('specialties.deleted_at')
            ->get(['doctor_specialty.doctor_id', 'doctor_specialty.source', 'specialties.id', 'specialties.name', 'specialties.slug'])
            ->groupBy('doctor_id');
        $numbers = $doctors->pluck('licence_number')->filter()->map(fn ($number): string => (string) $number)->values()->all();
        $licences = $numbers === [] ? collect() : DB::table('komora_licences')->whereIn('licence_number', $numbers)
            ->get(['id', 'licence_number', 'full_name', 'specialty', 'specialty_key', 'valid_until', 'missing_since'])
            ->keyBy('licence_number');
        $profilesWithoutLicence = DB::table('doctors')
            ->whereNull('deleted_at')
            ->whereNull('licence_number')
            ->whereIn('name_key_sorted', $doctors->map(fn (Doctor $doctor): string => NameKey::sorted((string) $doctor->full_name))->unique()->values()->all())
            ->groupBy('name_key_sorted')
            ->selectRaw('name_key_sorted, count(*) as aggregate')
            ->pluck('aggregate', 'name_key_sorted');

        $evidence = [];

        foreach ($doctors as $doctor) {
            $id = (int) $doctor->getKey();
            $own = $specialties->get($id, collect());
            $candidate = new LicenceCandidate(
                doctorId: $id,
                specialtyIds: $own->pluck('id')->map(fn ($value): int => (int) $value)->unique()->values()->all(),
                specialtyNames: $own->pluck('name')->map(fn ($value): string => (string) $value)->unique()->values()->all(),
                specialtySlugs: $own->pluck('slug')->map(fn ($value): string => (string) $value)->unique()->values()->all(),
                fallback: $doctor->fzo_facsimile === null && $doctor->import_source === InstitutionsJsonImporter::SOURCE,
            );
            $slugs = $candidate->specialtySlugs;
            $sortedName = NameKey::sorted((string) $doctor->full_name);
            $ownRecords = $records->get($id, collect());
            $fzomRecord = $ownRecords->firstWhere('source', FzomImporter::SOURCE);
            $ownLinks = $links->get($id, collect());
            $linkedFacilities = $ownLinks->pluck('facility_id')->map(fn ($value): int => (int) $value)->all();
            $attached = $doctor->licence_number !== null ? $licences->get((string) $doctor->licence_number) : null;

            $evidence[$id] = new DoctorEvidence(
                doctorId: $id,
                published: (bool) $doctor->is_published,
                reviewsCount: (int) ($doctor->reviews_count ?? 0),
                suppressed: $this->isSuppressed($doctor),
                ownerLinked: $doctor->owner_user_id !== null && $doctor->owner_linked_at !== null,
                fzomRecordId: $fzomRecord !== null && $doctor->fzo_facsimile !== null ? (int) $fzomRecord->id : null,
                fzomCurrent: $fzomRecord !== null && $doctor->fzo_facsimile !== null && (int) $doctor->import_missing_runs === 0
                    && $this->fzomCurrentSince !== null && CarbonImmutable::parse($fzomRecord->last_seen_at)->gte($this->fzomCurrentSince),
                fzomFacilityIds: $ownLinks->where('source', FzomImporter::SOURCE)->pluck('facility_id')->map(fn ($value): int => (int) $value)->values()->all(),
                fzomSpecialtyIds: $own->where('source', FzomImporter::SOURCE)->pluck('id')->map(fn ($value): int => (int) $value)->values()->all(),
                dental: $slugs !== [] && array_filter($slugs, fn (string $slug): bool => ! FzomSpecialtyCatalog::isDental($slug)) === [],
                licence: $attached !== null ? $this->licenceFacts($attached, $sortedName, $candidate) : null,
                stagedMismatch: $attached === null ? $this->stagedRow($id, $sortedName, $candidate, 'specialty_mismatch') : null,
                licenceAmbiguous: $attached === null && $this->stagedRow($id, $sortedName, $candidate, 'ambiguous') !== null,
                namesakeLicences: $attached === null ? $this->namesakeLicences($sortedName, $candidate, (int) ($profilesWithoutLicence[$sortedName] ?? 1)) : 0,
                websites: $ownRecords->where('source', InstitutionsJsonImporter::SOURCE)
                    ->map(fn (object $record): ?WebsiteFact => $this->websiteFact($record, $linkedFacilities))
                    ->filter()->values()->all(),
                specialtyNames: $candidate->specialtyNames,
                fzomStale: $this->fzomStale,
            );
        }

        return $evidence;
    }

    /**
     * @param  Collection<int, Facility>  $facilities
     * @return array<int, FacilityEvidence>
     */
    public function facilities(Collection $facilities): array
    {
        $ids = $facilities->map(fn (Facility $facility): int => (int) $facility->getKey())->values()->all();

        if ($ids === []) {
            return [];
        }

        $records = DB::table('source_records')
            ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
            ->whereIn('subject_id', $ids)
            ->whereIn('source', [FzomImporter::SOURCE, InstitutionsJsonImporter::SOURCE])
            ->orderBy('id')
            ->get(['id', 'source', 'external_key', 'subject_id', 'payload', 'last_seen_at'])
            ->groupBy('subject_id');

        $evidence = [];

        foreach ($facilities as $facility) {
            $id = (int) $facility->getKey();
            $own = $records->get($id, collect());

            $evidence[$id] = new FacilityEvidence(
                facilityId: $id,
                published: (bool) $facility->is_published,
                pharmacy: $facility->type === FacilityType::Pharmacy || $facility->getRawOriginal('type') === FacilityType::Pharmacy->value,
                register: $own->where('source', FzomImporter::SOURCE)
                    ->map(fn (object $record): RegisterFact => $this->registerFact($facility, $record))
                    ->values()->all(),
                fromWebsite: $own->contains('source', InstitutionsJsonImporter::SOURCE) || $facility->import_source === InstitutionsJsonImporter::SOURCE,
                registerStale: $this->fzomStale,
            );
        }

        return $evidence;
    }

    private function registerFact(Facility $facility, object $record): RegisterFact
    {
        $payload = self::payload($record);
        $key = (string) $record->external_key;
        $codes = array_map('strval', (array) ($payload['codes'] ?? []));

        $taxNumberMatches = match (true) {
            preg_match('/^facility:edb:([^:]+):/u', $key, $match) === 1 => $facility->tax_number !== null && (string) $facility->tax_number === $match[1],
            preg_match('/^facility:zu:(.+)$/u', $key, $match) === 1 => $facility->fzo_code !== null && in_array((string) $facility->fzo_code, [...$codes, $match[1]], true),
            default => false,
        };

        return new RegisterFact(
            recordId: (int) $record->id,
            current: (int) $facility->import_missing_runs === 0 && $this->fzomCurrentSince !== null
                && CarbonImmutable::parse($record->last_seen_at)->gte($this->fzomCurrentSince),
            taxNumberMatches: $taxNumberMatches,
            nameMatches: NameKey::sorted((string) $facility->name) === NameKey::sorted((string) ($payload['name'] ?? '')),
            townMatches: self::townKey($facility->city) !== '' && self::townKey($facility->city) === self::townKey(is_string($payload['town'] ?? null) ? $payload['town'] : null),
        );
    }

    /**
     * @param  list<int>  $linkedFacilities
     */
    private function websiteFact(object $record, array $linkedFacilities): ?WebsiteFact
    {
        if (preg_match('/^worker:(\d+):/', (string) $record->external_key, $match) !== 1) {
            return null;
        }

        $facilityId = (int) $match[1];
        $payload = self::payload($record);
        $stated = trim((string) ($payload['specialty'] ?? '')) !== '';
        $seen = CarbonImmutable::parse($record->last_seen_at);

        return new WebsiteFact(
            recordId: (int) $record->id,
            facilityId: $facilityId,
            linked: in_array($facilityId, $linkedFacilities, true),
            highConfidence: ($payload['confidence'] ?? null) === 'high',
            flags: $this->siteFlags[$facilityId] ?? [],
            trusted: isset($this->trustedSites[$facilityId]),
            specialtyIds: $stated ? array_map('intval', (array) ($payload['specialty_ids'] ?? [])) : null,
            onPage: ($siteSeen = $this->siteSeenFor($facilityId, $payload['slice'] ?? null)) === null || $seen->gte($siteSeen),
            recent: $seen->gte($this->websiteSince),
        );
    }

    /**
     * When the site was last imported by the slice that last listed the
     * entry: another slice describing the same facility (a different
     * research pass, imported later) says nothing about this entry. Entries
     * stored before the slice was recorded compare with the newest import
     * of any slice.
     */
    private function siteSeenFor(int $facilityId, mixed $slice): ?CarbonImmutable
    {
        if (is_string($slice) && $slice !== '') {
            $prefix = 'institution:'.$slice.':';
            $times = array_filter($this->siteSeenByRecord[$facilityId] ?? [], fn (string $key): bool => str_starts_with($key, $prefix), ARRAY_FILTER_USE_KEY);

            if ($times !== []) {
                return array_reduce($times, fn (?CarbonImmutable $max, CarbonImmutable $seen): CarbonImmutable => $max !== null && $max->gt($seen) ? $max : $seen);
            }
        }

        return $this->siteSeen[$facilityId] ?? null;
    }

    private function licenceFacts(object $row, string $sortedName, LicenceCandidate $candidate): LicenceFacts
    {
        $rowName = NameKey::sorted((string) $row->full_name);
        $groups = $this->map->licenceGroups($row->specialty);
        $namesakes = array_values(array_filter($this->listByName[$rowName] ?? [], fn (object $other): bool => (int) $other->id !== (int) $row->id));

        return new LicenceFacts(
            komoraLicenceId: (int) $row->id,
            onList: $row->missing_since === null,
            valid: $row->valid_until !== null && CarbonImmutable::parse($row->valid_until)->gte($this->today),
            nameAgrees: $rowName === $sortedName,
            fits: $groups !== null && $this->map->fits($groups, $candidate),
            uniqueFit: array_filter($namesakes, fn (object $other): bool => $this->fitsCandidate($other, $candidate)) === [],
            nameUnique: $namesakes === [],
            specialty: $row->specialty,
            specialtyKey: $row->specialty_key,
            general: $groups !== null && $this->map->isGeneral($groups),
        );
    }

    /**
     * An unattached row of the current list with the profile's name whose
     * matching outcome was $outcome and that named this profile.
     */
    private function stagedRow(int $doctorId, string $sortedName, LicenceCandidate $candidate, string $outcome): ?LicenceFacts
    {
        $rows = $this->listByName[$sortedName] ?? [];

        foreach ($rows as $row) {
            $candidates = array_map('intval', (array) json_decode((string) ($row->candidate_doctor_ids ?? '[]'), true));

            if ($row->doctor_id === null && $row->outcome === $outcome && in_array($doctorId, $candidates, true)
                && ($outcome !== 'specialty_mismatch' || $candidates === [$doctorId])) {
                return $this->licenceFacts($row, $sortedName, $candidate);
            }
        }

        return null;
    }

    /**
     * Several unattached rows of the current list carry the profile's name
     * and fit its specialties: if every one of them is valid and there are
     * at least as many as profiles of that name without a licence, each such
     * profile holds one of them, whichever. Returns how many (0 otherwise).
     */
    private function namesakeLicences(string $sortedName, LicenceCandidate $candidate, int $profiles): int
    {
        $fitting = array_values(array_filter(
            $this->listByName[$sortedName] ?? [],
            fn (object $row): bool => $row->doctor_id === null && $this->fitsCandidate($row, $candidate),
        ));

        $allValid = array_filter($fitting, fn (object $row): bool => $row->valid_until === null || CarbonImmutable::parse($row->valid_until)->lt($this->today)) === [];

        return count($fitting) >= 2 && count($fitting) >= $profiles && $allValid ? count($fitting) : 0;
    }

    private function fitsCandidate(object $row, LicenceCandidate $candidate): bool
    {
        $groups = $this->map->licenceGroups($row->specialty);

        return $groups !== null && $this->map->fits($groups, $candidate);
    }

    private function isSuppressed(Doctor $doctor): bool
    {
        return isset($this->suppressed['doctor_ids'][(int) $doctor->getKey()])
            || ($doctor->fzo_facsimile !== null && isset($this->suppressed['facsimiles'][(string) $doctor->fzo_facsimile]))
            || ($doctor->licence_number !== null && isset($this->suppressed['licences'][(string) $doctor->licence_number]));
    }

    /**
     * Start of the latest successful complete ФЗОМ apply; records it (or a
     * later run) listed are current. Without a complete one, the latest
     * successful apply of any kind (an import that never saw the whole
     * register still tells what it listed).
     *
     * Null — nothing from ФЗОМ is current — when ФЗОМ has not been heard from
     * (an apply, or a 304 confirming the snapshot) within
     * import.verification.fzom_max_age_days: the register is stale.
     */
    private function fzomSnapshotStart(): ?CarbonImmutable
    {
        $runs = ImportRun::query()
            ->where('source', FzomImporter::SOURCE)
            ->where('dry_run', false)
            ->where('status', ImportRunStatus::Succeeded)
            ->latest('id')
            ->limit(20)
            ->get();
        $run = $runs->first(fn (ImportRun $run): bool => (bool) ($run->source_meta['complete'] ?? true)) ?? $runs->first();

        if ($run?->started_at === null) {
            return null;
        }

        $heard = ImportRun::query()
            ->where('source', FzomImporter::SOURCE)
            ->where('dry_run', false)
            ->whereIn('status', [ImportRunStatus::Succeeded, ImportRunStatus::NotModified])
            ->max('started_at');
        $maxAge = max(1, (int) config('import.verification.fzom_max_age_days', 45));

        if ($heard === null || CarbonImmutable::parse($heard)->lt(CarbonImmutable::now()->subDays($maxAge))) {
            $this->fzomStale = true;

            return null;
        }

        return CarbonImmutable::parse($run->started_at);
    }

    private function loadList(): void
    {
        DB::table('komora_licences')
            ->whereNull('missing_since')
            ->orderBy('id')
            ->select(['id', 'full_name', 'specialty', 'specialty_key', 'valid_until', 'missing_since', 'doctor_id', 'outcome', 'candidate_doctor_ids'])
            ->lazyById(2000)
            ->each(function (object $row): void {
                $this->listByName[NameKey::sorted((string) $row->full_name)][] = $row;
            });
    }

    private function loadSuppressions(): void
    {
        ImportSuppression::query()->active()->get(['doctor_id', 'fzo_facsimile', 'licence_number'])
            ->each(function (ImportSuppression $suppression): void {
                if ($suppression->doctor_id !== null) {
                    $this->suppressed['doctor_ids'][(int) $suppression->doctor_id] = true;
                }

                if ($suppression->fzo_facsimile !== null) {
                    $this->suppressed['facsimiles'][(string) $suppression->fzo_facsimile] = true;
                }

                if ($suppression->licence_number !== null) {
                    $this->suppressed['licences'][(string) $suppression->licence_number] = true;
                }
            });
    }

    /**
     * Warnings the website import recorded per facility, and the flagged
     * sites staff decided to trust.
     */
    private function loadSites(): void
    {
        DB::table('source_records')
            ->where('source', InstitutionsJsonImporter::SOURCE)
            ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
            ->whereNotNull('subject_id')
            ->get(['subject_id', 'external_key', 'payload', 'last_seen_at'])
            ->each(function (object $record): void {
                $payload = self::payload($record);

                if (str_starts_with((string) $record->external_key, 'institution:') && $record->last_seen_at !== null) {
                    $id = (int) $record->subject_id;
                    $seen = CarbonImmutable::parse($record->last_seen_at);
                    $this->siteSeen[$id] = isset($this->siteSeen[$id]) && $this->siteSeen[$id]->gt($seen) ? $this->siteSeen[$id] : $seen;
                    $this->siteSeenByRecord[$id][(string) $record->external_key] = $seen;
                }

                $flags = array_values(array_filter((array) ($payload['source_flags'] ?? []), 'is_string'));

                if ($flags !== []) {
                    $id = (int) $record->subject_id;
                    $this->siteFlags[$id] = array_values(array_unique([...($this->siteFlags[$id] ?? []), ...$flags]));
                    $this->siteFlagNotes[$id] ??= is_string($payload['source_flag_note'] ?? null) ? $payload['source_flag_note'] : null;
                }
            });

        ImportReviewItem::query()
            ->where('source', self::ENGINE_SOURCE)
            ->where('kind', ImportReviewKind::Uncertain)
            ->where('status', ImportReviewStatus::Resolved)
            ->where('resolution', self::TRUSTED_RESOLUTION)
            ->where('item_key', 'like', self::FLAGGED_SITE_KEY.'%')
            ->pluck('item_key')
            ->each(function (string $key): void {
                $this->trustedSites[(int) substr($key, strlen(self::FLAGGED_SITE_KEY))] = true;
            });
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(object $record): array
    {
        $payload = is_string($record->payload) ? json_decode($record->payload, true) : $record->payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * The town part of "Скопје - Карпош" (the register adds the municipality).
     */
    private static function townKey(?string $town): string
    {
        return NameKey::for((string) (preg_split('/\s+[-–—]\s+/u', trim((string) $town))[0] ?? ''));
    }
}
