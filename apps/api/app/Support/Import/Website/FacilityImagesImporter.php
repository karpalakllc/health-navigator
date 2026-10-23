<?php

namespace App\Support\Import\Website;

use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Support\Import\ImportContext;
use App\Support\Import\NameKey;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attaches a logo and cover photos to EXISTING facilities by id — clinics,
 * hospitals, labs and pharmacies alike (images.json, docs/data-import.md §6b).
 * Unlike import:institutions-json it matches nothing by name and creates
 * nothing: an entry names its facility.
 *
 * - Only fills: a facility that already has a logo (avatar_url) or a cover
 *   (cover_path), or whose field staff locked (a takedown locks it), keeps
 *   it; nothing is stored for that field.
 * - Storage, statuses, source URLs and removal suppression are the website
 *   import's (FacilityImageStore). Provenance source "website", the image's
 *   source URL per field.
 * - Skipped and reported: a facility that is missing, deleted or merged; a
 *   name that does not roughly match the facility's; confidence other than
 *   "high" (needs review: nothing imported); a file that is not a JPEG, PNG
 *   or WebP of at least 64 px (logo) or 600 px wide (cover).
 * - official_url, when it is the facility's own site (not a social page),
 *   fills an empty website field.
 */
final class FacilityImagesImporter
{
    public const SOURCE = FacilityImageStore::SOURCE;

    public const MIN_LOGO_PX = 64;

    public const MIN_COVER_WIDTH = 600;

    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** Hosts whose pages are social profiles, not the facility's own site. */
    private const SOCIAL_HOSTS = ['facebook.com', 'fb.com', 'fb.me', 'instagram.com', 'tiktok.com', 'linkedin.com', 'youtube.com', 'youtu.be', 'twitter.com', 'x.com', 'threads.net'];

    /**
     * Entries not (fully) imported, for the command's report.
     *
     * @var list<array{facility_id: string, name: string, outcome: string}>
     */
    private array $report = [];

    public function __construct(private readonly FacilityImageStore $images) {}

    /**
     * @return list<array{facility_id: string, name: string, outcome: string}>
     */
    public function report(): array
    {
        return $this->report;
    }

    public function import(ImportContext $context, string $jsonPath): void
    {
        $data = json_decode((string) file_get_contents($jsonPath), true, 64, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! is_array($data['facilities'] ?? null)) {
            throw new RuntimeException('Not an images.json file: no "facilities" list.');
        }

        $this->report = [];
        $baseDir = dirname($jsonPath);
        $provenance = new ProvenanceWriter($context);

        foreach (array_chunk($data['facilities'], 50) as $batch) {
            DB::transaction(function () use ($context, $provenance, $batch, $baseDir): void {
                foreach ($batch as $entry) {
                    is_array($entry) ? $this->importEntry($context, $provenance, $entry, $baseDir) : $context->increment('entries_invalid');
                }
            });
        }

        $provenance->flushChanges();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function importEntry(ImportContext $context, ProvenanceWriter $p, array $entry, string $baseDir): void
    {
        $context->increment('entries_in_source');
        $id = filter_var($entry['facility_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';

        if ($id === false) {
            $this->skip($context, 'entries_invalid', (string) json_encode($entry['facility_id'] ?? null), $name, 'no valid facility_id');

            return;
        }

        $facility = Facility::withTrashed()->find($id);

        if ($facility === null) {
            $this->skip($context, 'facilities_missing', (string) $id, $name, 'no such facility');

            return;
        }

        if ($facility->trashed() || $facility->getAttribute('merged_into_id') !== null) {
            $this->skip($context, 'facilities_merged_or_deleted', (string) $id, $name, $facility->getAttribute('merged_into_id') !== null
                ? 'merged into #'.$facility->getAttribute('merged_into_id')
                : 'deleted');

            return;
        }

        if (! self::namesMatch($name, (string) $facility->name, $facility->city)) {
            $this->skip($context, 'names_mismatched', (string) $id, $name, 'name does not match „'.$facility->name.'“');

            return;
        }

        if (($entry['confidence'] ?? null) !== 'high') {
            $this->skip($context, 'needs_review', (string) $id, $name, 'confidence '.(is_string($entry['confidence'] ?? null) ? $entry['confidence'] : 'missing').': not imported');

            return;
        }

        $context->increment('facilities_matched');
        $label = (string) $facility->name.($facility->city !== null ? ', '.$facility->city : '');
        $officialUrl = FacilityImageStore::url($entry['official_url'] ?? null);

        if ($officialUrl !== null && ! self::isSocial($officialUrl) && $facility->website === null) {
            if ($p->from($officialUrl)->scalar($facility, 'website', $officialUrl, false, null, $label) === 'written') {
                $context->increment('websites_filled');
            }
        }

        $logo = is_array($entry['logo'] ?? null) ? $entry['logo'] : null;

        if ($logo !== null && $this->canFill($context, $p, $facility, 'avatar_url', 'logos')) {
            $media = $this->storeValid($context, $facility, FacilityMedia::KIND_LOGO, $logo, $baseDir, 0);

            if ($media?->path !== null && $this->images->point($p, $facility, 'avatar_url', $media, null, $label) === 'written') {
                $context->increment('logos_filled');
            }
        }

        $covers = array_values(array_filter((array) ($entry['covers'] ?? []), 'is_array'));

        if ($covers !== [] && $this->canFill($context, $p, $facility, 'cover_path', 'covers')) {
            $first = null;

            foreach ($covers as $position => $cover) {
                $media = $this->storeValid($context, $facility, FacilityMedia::KIND_COVER, $cover, $baseDir, $position);
                $first ??= $media?->path !== null ? $media : null;
            }

            if ($first !== null && $this->images->point($p, $facility, 'cover_path', $first, null, $label) === 'written') {
                $context->increment('covers_filled');
            }
        }

        if ($facility->isDirty()) {
            $facility->save();
        }
    }

    /**
     * Only an empty field staff did not lock is filled; nothing is stored
     * otherwise.
     */
    private function canFill(ImportContext $context, ProvenanceWriter $p, Facility $facility, string $column, string $prefix): bool
    {
        if ($p->isLocked($facility, $column)) {
            $context->increment($prefix.'_skipped_locked');

            return false;
        }

        if ($facility->getAttribute($column) !== null && $facility->getAttribute($column) !== '') {
            $context->increment($prefix.'_skipped_existing');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function storeValid(ImportContext $context, Facility $facility, string $kind, array $image, string $baseDir, int $position): ?FacilityMedia
    {
        $path = FacilityImageStore::resolveFile($context, $baseDir, $image['file'] ?? null);

        if ($path === null) {
            return null;
        }

        $size = @getimagesize($path);

        if ($size === false || ! in_array($size[2], self::ALLOWED_TYPES, true)) {
            $context->increment('images_not_an_image');
            $context->record('facility', 'image_rejected', $facility, (string) $facility->name, $kind, null, basename($path), 'Not a JPEG, PNG or WebP image.');

            return null;
        }

        [$width, $height] = $size;
        $tooSmall = $kind === FacilityMedia::KIND_LOGO
            ? min($width, $height) < self::MIN_LOGO_PX
            : $width < self::MIN_COVER_WIDTH;

        if ($tooSmall) {
            $context->increment('images_too_small');
            $context->record('facility', 'image_rejected', $facility, (string) $facility->name, $kind, null, basename($path), "Too small ({$width}×{$height}).");

            return null;
        }

        return $this->images->storeFile($context, $facility, $kind, $path, FacilityImageStore::url($image['source_url'] ?? null), $position);
    }

    private function skip(ImportContext $context, string $count, string $id, string $name, string $outcome): void
    {
        $context->increment($count);
        $context->record('facility', $count === 'needs_review' ? 'needs_review' : 'skipped', null, $name !== '' ? $name : '#'.$id, 'facility_id', null, $id, $outcome);
        $this->report[] = ['facility_id' => $id, 'name' => $name, 'outcome' => $outcome];
    }

    /**
     * A sanity check against a wrong id, not a matcher: the significant
     * words (no legal form, no town, no punctuation) of one name are all in
     * the other, or at least half of all their words are shared.
     */
    public static function namesMatch(string $given, string $actual, ?string $town): bool
    {
        $townWords = explode(' ', NameKey::for((string) (preg_split('/\s+[-–—]\s+/u', trim((string) $town))[0] ?? '')));
        $tokens = fn (string $name): array => array_values(array_unique(array_filter(
            explode(' ', NameKey::for($name)),
            fn (string $word): bool => $word !== '' && ! in_array($word, InstitutionsJsonImporter::LEGAL_WORDS, true) && ! in_array($word, $townWords, true),
        )));

        $a = $tokens($given);
        $b = $tokens($actual);

        // A name that is only a legal form and a town: compare everything.
        if ($a === [] || $b === []) {
            return NameKey::for($given) !== '' && NameKey::for($given) === NameKey::for($actual);
        }

        $shared = count(array_intersect($a, $b));

        return $shared === count($a) || $shared === count($b) || $shared * 2 >= count(array_unique([...$a, ...$b]));
    }

    private static function isSocial(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (self::SOCIAL_HOSTS as $social) {
            if ($host === $social || str_ends_with($host, '.'.$social)) {
                return true;
            }
        }

        return false;
    }
}
