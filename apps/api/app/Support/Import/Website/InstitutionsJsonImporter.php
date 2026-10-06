<?php

namespace App\Support\Import\Website;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Models\FieldProvenance;
use App\Models\SourceRecord;
use App\Support\Import\DirectoryWriter;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;
use App\Support\Import\ImportContext;
use App\Support\Import\NameKey;
use App\Support\Import\ProvenanceWriter;
use App\Support\Import\SpecialtyResolver;
use App\Support\Import\TextCase;
use App\Support\Media\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Imports institutions and their listed doctors compiled from the
 * institutions' own websites (one institutions.json per research slice).
 *
 * - Facilities: matched to existing ones (ФЗОМ or staff) by website host,
 *   then by normalised name + town; several matches go to review; else a
 *   hidden draft. On a matched record the website only FILLS fields the
 *   register does not provide or that are empty; it never fights the
 *   register over the name or address.
 * - Logo → the facility avatar (status "trademark-identification"); cover
 *   candidates are stored, the first one becomes the cover (owner decision,
 *   status "website"), the others stay as alternatives in the admin. Every
 *   image keeps its source URL; removed images never come back.
 * - Workers: physicians and dentists only. Matched to existing doctors by
 *   normalised name at this facility, then name + specialty + town;
 *   ambiguous → review; otherwise a hidden draft queued for licence
 *   verification. Links are only ever added.
 * - Provenance: source "website", source URL per field.
 *
 * A dry run stores no image files (the database is rolled back, files
 * would be orphaned); it counts what would be stored.
 */
final class InstitutionsJsonImporter
{
    public const SOURCE = 'website';

    private const HOSPITAL_TYPES = ['hospital', 'university_clinic', 'clinical_hospital', 'general_hospital', 'special_hospital', 'private_hospital'];

    private const LAB_TYPES = ['laboratory', 'diagnostic_centre'];

    private const WORKER_ROLES = ['physician', 'dentist'];

    /** Legal-form words ignored when comparing institution names. */
    private const LEGAL_WORDS = ['ЈЗУ', 'ПЗУ', 'ЗУ', 'ДООЕЛ', 'ДОО', 'ЈЗО', 'АД', 'ТП', 'СКОПЈЕ'];

    /** @var array<string, list<int>> */
    private array $byHost = [];

    /** @var array<string, array<int, list<string>>> town key => facility id => name words */
    private array $byTown = [];

    public function __construct(private readonly ImageOptimizer $images) {}

    public function import(ImportContext $context, string $jsonPath): void
    {
        $data = json_decode((string) file_get_contents($jsonPath), true, 64, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! is_array($data['institutions'] ?? null)) {
            throw new RuntimeException('Not an institutions.json file: no "institutions" list.');
        }

        $slice = (string) ($data['slice'] ?? basename(dirname($jsonPath)));
        $baseDir = dirname($jsonPath);
        $provenance = new ProvenanceWriter($context);
        $writer = new DirectoryWriter($context, $provenance);
        $specialties = new SpecialtyResolver($context, self::SOURCE);
        $this->indexFacilities();

        foreach (array_chunk($data['institutions'], max(1, (int) config('import.batch_size') / 10)) as $batch) {
            DB::transaction(function () use ($context, $writer, $specialties, $batch, $slice, $baseDir): void {
                foreach ($batch as $institution) {
                    if (! is_array($institution) || trim((string) ($institution['name_mk'] ?? '')) === '') {
                        $context->increment('institutions_invalid');

                        continue;
                    }

                    $context->increment('institutions_in_source');
                    $facility = $this->importInstitution($context, $writer, $slice, $institution, $baseDir);

                    if ($facility === null) {
                        continue;
                    }

                    foreach ((array) ($institution['workers'] ?? []) as $worker) {
                        if (is_array($worker)) {
                            $this->importWorker($context, $writer, $specialties, $facility, $worker);
                        }
                    }
                }
            });
        }

        $provenance->flushChanges();
    }

    /**
     * @param  array<string, mixed>  $institution
     */
    private function importInstitution(ImportContext $context, DirectoryWriter $writer, string $slice, array $institution, string $baseDir): ?Facility
    {
        $key = 'institution:'.$slice.':'.mb_substr((string) ($institution['slug'] ?? NameKey::for((string) $institution['name_mk'])), 0, 80);
        $name = TextCase::institution(trim((string) $institution['name_mk']));
        $town = TextCase::place(self::str($institution['town'] ?? null));
        $type = match (true) {
            in_array($institution['type'] ?? null, self::HOSPITAL_TYPES, true) => FacilityType::Hospital,
            in_array($institution['type'] ?? null, self::LAB_TYPES, true) => FacilityType::Laboratory,
            default => FacilityType::Clinic,
        };
        $website = self::url($institution['website'] ?? null);
        $sourceUrl = self::url(($institution['sources'] ?? [])[0] ?? null) ?? $website;
        $ownership = in_array($institution['ownership'] ?? null, ['public', 'private'], true) ? (string) $institution['ownership'] : null;
        $phones = array_values(array_filter(array_map(fn ($phone): ?string => self::str($phone), (array) ($institution['phones'] ?? []))));

        $payload = array_intersect_key($institution, array_flip([
            'name_mk', 'name_latin', 'type', 'ownership', 'legal_form', 'address', 'town', 'municipality',
            'phones', 'email', 'website', 'hours', 'departments', 'fzom_contract', 'social', 'sources', 'confidence',
        ]));
        $payload['logo'] = $institution['logo']['source_url'] ?? null;
        $payload['covers'] = array_map(fn ($cover) => is_array($cover) ? ($cover['source_url'] ?? null) : null, (array) ($institution['covers'] ?? []));

        $sourceRecord = SourceRecord::query()->where('source', self::SOURCE)->where('external_key', $key)->first();
        $stored = $writer->sourceRecord($key, FieldProvenance::SUBJECT_FACILITY, $payload, $sourceRecord);
        $facility = $sourceRecord?->subject_id !== null ? Facility::withTrashed()->find($sourceRecord->subject_id) : null;

        if ($facility !== null && $stored['unchanged']) {
            $context->increment('facilities_unchanged');

            return $facility;
        }

        if ($facility === null) {
            $match = $this->facilityCandidates($name, $town, $website);
            $candidates = $match['ids'];

            if (count($candidates) > 1) {
                $context->increment('facilities_ambiguous');
                $context->review(ImportReviewKind::Unmatched, 'website-facility:'.$key, 'Several facilities match '.$name, [
                    'reason' => 'ambiguous_facility', 'candidate_facility_ids' => $candidates, 'source_url' => $sourceUrl,
                ]);

                return null;
            }

            $facility = $candidates !== [] ? Facility::query()->find($candidates[0]) : null;

            if ($facility !== null && $match['fuzzy']) {
                $context->increment('facilities_matched_by_partial_name');
                $context->review(ImportReviewKind::Unmatched, 'website-facility-partial:'.$key, 'Check: '.$name.' matched to '.$facility->name, [
                    'reason' => 'partial_name_match', 'facility_id' => $facility->getKey(), 'source_url' => $sourceUrl,
                ], $facility);
            }
        }

        $isNew = $facility === null;
        $facility ??= $writer->newFacility($name, $town, $type);
        $label = $name.($town !== null ? ', '.$town : '');
        $recordId = (int) $stored['record']->getKey();
        $p = $writer->provenance()->from($sourceUrl);

        $fill = fn (string $field, ?string $value) => ($isNew || $facility->getAttribute($field) === null || $p->sourceOf($facility, $field) === self::SOURCE)
            ? $p->scalar($facility, $field, $value, $isNew, $recordId, $label)
            : 'unchanged';

        $fill('name', $name);
        $fill('city', $town);
        $fill('address', TextCase::place(self::str($institution['address'] ?? null)));
        $fill('ownership', $ownership);
        $p->scalar($facility, 'phone', $phones[0] ?? null, $isNew, $recordId, $label);
        $p->scalar($facility, 'email', self::email($institution['email'] ?? null), $isNew, $recordId, $label);
        $p->scalar($facility, 'website', $website, $isNew, $recordId, $label);

        if ($isNew) {
            $facility->forceFill(['import_source' => self::SOURCE]);
        }

        $writer->save($facility);
        $stored['record']->forceFill(['subject_id' => $facility->getKey()])->save();
        $this->remember($facility);

        $this->importImages($context, $p, $facility, $institution, $baseDir, $label, $recordId);
        $facility->isDirty() ? $writer->save($facility) : null;

        if ($isNew) {
            $context->increment('facilities_created');
            $context->record('facility', 'create', $facility, $label, 'source', null, $sourceUrl);
            $context->review(ImportReviewKind::New, 'facility:'.$facility->getKey(), $label, ['source' => self::SOURCE, 'source_url' => $sourceUrl], $facility);
        } else {
            $context->increment('facilities_matched');
        }

        return $facility;
    }

    /**
     * @param  array<string, mixed>  $institution
     */
    private function importImages(ImportContext $context, ProvenanceWriter $p, Facility $facility, array $institution, string $baseDir, string $label, int $recordId): void
    {
        $logo = is_array($institution['logo'] ?? null) ? $institution['logo'] : null;

        if ($logo !== null) {
            $media = $this->storeImage($context, $facility, FacilityMedia::KIND_LOGO, $logo, $baseDir, 0);

            if ($media?->path !== null) {
                $this->setImage($p, $facility, 'avatar_url', $media, $recordId, $label);
            }
        }

        $first = null;

        foreach (array_values(array_filter((array) ($institution['covers'] ?? []), 'is_array')) as $position => $cover) {
            $media = $this->storeImage($context, $facility, FacilityMedia::KIND_COVER, $cover, $baseDir, $position);
            $first ??= $media?->path !== null ? $media : null;
        }

        if ($first !== null) {
            $this->setImage($p, $facility, 'cover_path', $first, $recordId, $label);
        }
    }

    /**
     * Points the facility at an imported image. Saving replaces the column,
     * and DeletesReplacedMedia deletes the previous file: if that was an
     * earlier imported image, its row is marked replaced so nobody can
     * switch back to a file that no longer exists.
     */
    private function setImage(ProvenanceWriter $p, Facility $facility, string $column, FacilityMedia $media, int $recordId, string $label): void
    {
        $previous = $facility->getAttribute($column);

        if ($p->from($media->source_url)->scalar($facility, $column, $media->path, false, $recordId, $label) !== 'written' || $previous === null) {
            return;
        }

        FacilityMedia::query()->where('facility_id', $facility->getKey())->where('path', $previous)
            ->update(['path' => null, 'status' => FacilityMedia::STATUS_REPLACED, 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function storeImage(ImportContext $context, Facility $facility, string $kind, array $image, string $baseDir, int $position): ?FacilityMedia
    {
        $relative = (string) ($image['file'] ?? '');
        $path = realpath($baseDir.'/'.$relative);

        // Only files inside the dataset folder.
        if ($relative === '' || $path === false || ! str_starts_with($path, (string) realpath($baseDir)) || ! is_file($path)) {
            $context->increment('images_missing_file');

            return null;
        }

        if (filesize($path) > (int) config('import.website.max_image_bytes')) {
            $context->increment('images_too_large');

            return null;
        }

        $hash = (string) hash_file('sha256', $path);
        $existing = FacilityMedia::query()->where('facility_id', $facility->getKey())->where('kind', $kind)->where('content_hash', $hash)->first();

        if ($existing !== null) {
            $context->increment($existing->isRemoved() ? 'images_skipped_removed' : 'images_unchanged');

            return $existing->isRemoved() ? null : $existing;
        }

        if ($context->dryRun) {
            $context->increment($kind === FacilityMedia::KIND_LOGO ? 'logos_would_store' : 'covers_would_store');

            return null;
        }

        try {
            $file = new UploadedFile($path, basename($path), null, null, true);
            $stored = $kind === FacilityMedia::KIND_LOGO
                ? $this->images->storeBranding($file, 'facilities')
                : $this->images->store($file, 'facilities/covers', 1600, 900);
        } catch (Throwable $exception) {
            $context->increment('images_rejected');
            $context->record('facility', 'image_rejected', $facility, (string) $facility->name, $kind, null, null, $exception->getMessage());

            return null;
        }

        $context->increment($kind === FacilityMedia::KIND_LOGO ? 'logos_stored' : 'covers_stored');

        return FacilityMedia::query()->create([
            'facility_id' => $facility->getKey(),
            'kind' => $kind,
            'source' => self::SOURCE,
            'source_url' => self::url($image['source_url'] ?? null),
            'content_hash' => $hash,
            'path' => $stored,
            'status' => $kind === FacilityMedia::KIND_LOGO ? FacilityMedia::STATUS_TRADEMARK : FacilityMedia::STATUS_WEBSITE,
            'position' => $position,
        ]);
    }

    /**
     * @param  array<string, mixed>  $worker
     */
    private function importWorker(ImportContext $context, DirectoryWriter $writer, SpecialtyResolver $specialties, Facility $facility, array $worker): void
    {
        $context->increment('workers_in_source');
        $role = (string) ($worker['role'] ?? '');

        if (! in_array($role, self::WORKER_ROLES, true)) {
            $context->increment('workers_excluded_role');

            return;
        }

        $rawName = trim((string) ($worker['full_name'] ?? ''));
        $nameKey = NameKey::for($rawName);

        if ($nameKey === '' || ! str_contains($nameKey, ' ')) {
            $context->increment('workers_invalid_name');

            return;
        }

        $fullName = TextCase::person(self::withoutTitles($rawName));
        $sourceUrl = self::url($worker['source_url'] ?? null);
        $resolved = $specialties->resolve(self::str($worker['specialty'] ?? null));
        $specialtyIds = $resolved['ids'];

        if ($specialtyIds === [] && $role === 'dentist') {
            $specialtyIds = [$specialties->specialtyId(FzomSpecialtyCatalog::DENTAL_SLUG_PREFIX)];
        }

        $key = 'worker:'.$facility->getKey().':'.$nameKey;
        $payload = array_intersect_key($worker, array_flip(['full_name', 'title', 'role', 'specialty', 'department', 'source_url', 'seen_at', 'confidence']));
        $sourceRecord = SourceRecord::query()->where('source', self::SOURCE)->where('external_key', mb_substr($key, 0, 191))->first();
        $stored = $writer->sourceRecord(mb_substr($key, 0, 191), FieldProvenance::SUBJECT_DOCTOR, $payload + ['specialty_ids' => $specialtyIds], $sourceRecord);
        $doctor = $sourceRecord?->subject_id !== null ? Doctor::withTrashed()->find($sourceRecord->subject_id) : null;

        if ($doctor !== null && $stored['unchanged']) {
            $context->increment('doctors_unchanged');

            return;
        }

        if ($doctor === null) {
            $candidates = $this->doctorCandidates($fullName, (int) $facility->getKey(), $specialtyIds, $facility->city);

            if (count($candidates) > 1) {
                $context->increment('doctors_ambiguous');
                $context->review(ImportReviewKind::Unmatched, 'website-doctor:'.$stored['record']->getKey(), 'Several existing profiles match '.$fullName, [
                    'reason' => 'ambiguous_name', 'candidate_doctor_ids' => $candidates, 'facility_id' => $facility->getKey(), 'source_url' => $sourceUrl,
                ]);

                return;
            }

            $doctor = $candidates !== [] ? Doctor::query()->find($candidates[0]) : null;
        }

        $isNew = $doctor === null;
        $doctor ??= $writer->newDoctor($fullName, $facility->city);
        $label = $fullName.' — '.$facility->name;
        $p = $writer->provenance()->from($sourceUrl);
        $recordId = (int) $stored['record']->getKey();

        if ($isNew || $p->sourceOf($doctor, 'full_name') === self::SOURCE) {
            $p->scalar($doctor, 'full_name', $fullName, $isNew, $recordId, $label);
        }

        if ($isNew || $doctor->city === null) {
            $p->scalar($doctor, 'city', $facility->city, $isNew, $recordId, $label);
        }

        $p->scalar($doctor, 'title', self::title($worker['title'] ?? null), $isNew, $recordId, $label);

        $writer->save($doctor);
        $stored['record']->forceFill(['subject_id' => $doctor->getKey()])->save();
        $writer->addSpecialties($doctor, $specialtyIds, $isNew);
        $writer->linkFacility($doctor, (int) $facility->getKey(), self::str($worker['department'] ?? null), $isNew);

        if ($isNew) {
            $context->increment($role === 'dentist' ? 'dentists_created' : 'doctors_created');
            $context->record('doctor', 'create', $doctor, $label, 'source', null, $sourceUrl);
            $context->review(ImportReviewKind::New, 'doctor:'.$doctor->getKey(), $label, [
                'source' => self::SOURCE,
                'source_url' => $sourceUrl,
                'needs_licence_verification' => $role !== 'dentist',
            ], $doctor);
        } else {
            $context->increment('doctors_matched');
        }
    }

    /**
     * @param  list<int>  $specialtyIds
     * @return list<int>
     */
    private function doctorCandidates(string $fullName, int $facilityId, array $specialtyIds, ?string $town): array
    {
        $byName = Doctor::query()
            ->where(fn ($query) => $query->where('name_key', NameKey::for($fullName))->orWhere('name_key_sorted', NameKey::sorted($fullName)))
            ->get(['id', 'city']);

        if ($byName->isEmpty()) {
            return [];
        }

        $ids = $byName->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $atFacility = DB::table('doctor_facility')->whereIn('doctor_id', $ids)->where('facility_id', $facilityId)->pluck('doctor_id')->map(fn ($id): int => (int) $id)->all();

        if ($atFacility !== []) {
            return array_values(array_unique($atFacility));
        }

        $sameSpecialty = DB::table('doctor_specialty')->whereIn('doctor_id', $ids)->whereIn('specialty_id', $specialtyIds ?: [0])->pluck('doctor_id');
        $townKey = self::townKey($town);

        return $byName
            ->filter(fn (Doctor $doctor): bool => $sameSpecialty->contains($doctor->id) && $townKey !== '' && self::townKey($doctor->city) === $townKey)
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    private function indexFacilities(): void
    {
        $this->byHost = [];
        $this->byTown = [];

        Facility::query()->clinical()->get(['id', 'name', 'city', 'website'])->each(fn (Facility $facility) => $this->remember($facility));
    }

    private function remember(Facility $facility): void
    {
        $id = (int) $facility->getKey();
        $host = self::host($facility->website);

        if ($host !== null && ! in_array($id, $this->byHost[$host] ?? [], true)) {
            $this->byHost[$host][] = $id;
        }

        $town = self::townKey($facility->city);
        $this->byTown[$town][$id] = self::nameTokens((string) $facility->name, $town);
    }

    /**
     * Same website host; else, in the same town, the same significant name
     * words; else the one facility whose name words (at least three) are all
     * in the website's longer name ("… за кардиологија" in "… за
     * кардиологија и кардиоваскуларна хирургија"), reported for checking.
     *
     * @return array{ids: list<int>, fuzzy: bool}
     */
    private function facilityCandidates(string $name, ?string $town, ?string $website): array
    {
        $host = self::host($website);

        if ($host !== null && isset($this->byHost[$host])) {
            return ['ids' => $this->byHost[$host], 'fuzzy' => false];
        }

        $townKey = self::townKey($town);
        $tokens = self::nameTokens($name, $townKey);
        $exact = [];
        $contained = [];

        foreach ($this->byTown[$townKey] ?? [] as $id => $candidate) {
            if ($candidate === $tokens) {
                $exact[] = $id;
            } elseif (count($candidate) >= 3 && array_diff($candidate, $tokens) === []) {
                $contained[] = $id;
            }
        }

        if ($exact !== []) {
            return ['ids' => $exact, 'fuzzy' => false];
        }

        return ['ids' => count($contained) === 1 ? $contained : [], 'fuzzy' => count($contained) === 1];
    }

    /**
     * The town part of "Скопје - Карпош" (the register adds the municipality).
     */
    private static function townKey(?string $town): string
    {
        $first = preg_split('/\s+[-–—]\s+/u', trim((string) $town))[0] ?? '';

        return NameKey::for((string) $first);
    }

    /**
     * Significant words of an institution name, sorted: no legal form, no
     * town, no punctuation or quotes.
     *
     * @return list<string>
     */
    private static function nameTokens(string $name, string $townKey): array
    {
        $town = $townKey === '' ? [] : explode(' ', $townKey);
        $tokens = array_values(array_unique(array_filter(
            explode(' ', NameKey::for($name)),
            fn (string $token): bool => $token !== '' && ! in_array($token, self::LEGAL_WORDS, true) && ! in_array($token, $town, true),
        )));
        sort($tokens, SORT_STRING);

        return $tokens;
    }

    private static function host(?string $url): ?string
    {
        $host = $url !== null ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? (string) preg_replace('/^www\./', '', mb_strtolower($host)) : null;
    }

    private static function url(mixed $value): ?string
    {
        $value = self::str($value);

        return $value !== null && preg_match('#^https?://#i', $value) === 1 ? mb_substr($value, 0, 2000) : null;
    }

    private static function email(mixed $value): ?string
    {
        $value = self::str($value);

        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? mb_strtolower($value) : null;
    }

    private static function str(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private static function title(mixed $value): ?string
    {
        $title = self::str($value);

        return $title !== null ? mb_substr($title, 0, 60) : null;
    }

    /**
     * "проф. д-р Ана Петрова" → "Ана Петрова" (the title goes to doctors.title).
     */
    private static function withoutTitles(string $name): string
    {
        return trim((string) preg_replace('/^(?:(?:проф|доц|асс?|прим|спец|субспец|д-р|м-р|др|мр)(?:\.\s*|\s+))+/iu', '', $name));
    }
}
