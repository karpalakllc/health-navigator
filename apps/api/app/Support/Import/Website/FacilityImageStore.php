<?php

namespace App\Support\Import\Website;

use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Support\Import\ImportContext;
use App\Support\Import\ProvenanceWriter;
use App\Support\Media\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Stores a facility image taken from its own website (or official social
 * page) as a `facility_media` row, and points the facility at it. Shared by
 * import:institutions-json and import:facility-images.
 *
 * - Logo: stored as is (ImageOptimizer::storeBranding), status
 *   "trademark-identification"; covers: re-encoded to WebP ≤ 1600×900,
 *   status "website". Every row keeps its source URL and content hash.
 * - A removed image never comes back: the same file (content hash) or the
 *   same source URL of the same kind at this facility, once removed, is
 *   skipped.
 * - A dry run stores no files (the database is rolled back; files would be
 *   orphaned); it counts what would be stored.
 */
final class FacilityImageStore
{
    public const SOURCE = 'website';

    public function __construct(private readonly ImageOptimizer $images) {}

    /**
     * The image file inside the dataset folder, or null (counted as
     * `images_missing_file`) when it is missing or outside the folder.
     */
    public static function resolveFile(ImportContext $context, string $baseDir, mixed $relative): ?string
    {
        $relative = is_string($relative) ? $relative : '';
        $path = realpath($baseDir.'/'.$relative);
        $root = rtrim((string) realpath($baseDir), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        // Only files inside the dataset folder.
        if ($relative === '' || $path === false || ! str_starts_with($path, $root) || ! is_file($path)) {
            $context->increment('images_missing_file');

            return null;
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $image  {file, source_url}
     */
    public function store(ImportContext $context, Facility $facility, string $kind, array $image, string $baseDir, int $position): ?FacilityMedia
    {
        $path = self::resolveFile($context, $baseDir, $image['file'] ?? null);

        return $path !== null ? $this->storeFile($context, $facility, $kind, $path, self::url($image['source_url'] ?? null), $position) : null;
    }

    /**
     * Stores an already-resolved file (see resolveFile()).
     */
    public function storeFile(ImportContext $context, Facility $facility, string $kind, string $path, ?string $sourceUrl, int $position): ?FacilityMedia
    {
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

        // The site may serve a re-encoded file under the same address: a
        // takedown is of the image at that address, not of its bytes.
        if ($sourceUrl !== null && FacilityMedia::query()->where('facility_id', $facility->getKey())->where('kind', $kind)
            ->where('source_url', $sourceUrl)->where('status', FacilityMedia::STATUS_REMOVED)->exists()) {
            $context->increment('images_skipped_removed');

            return null;
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
            'source_url' => $sourceUrl,
            'content_hash' => $hash,
            'path' => $stored,
            'status' => $kind === FacilityMedia::KIND_LOGO ? FacilityMedia::STATUS_TRADEMARK : FacilityMedia::STATUS_WEBSITE,
            'position' => $position,
        ]);
    }

    /**
     * Points the facility at an imported image (provenance: the image's
     * source URL). Saving replaces the column, and DeletesReplacedMedia
     * deletes the previous file: if that was an earlier imported image, its
     * row is marked replaced so nobody can switch back to a file that no
     * longer exists. The caller saves the facility.
     *
     * @return 'written'|'unchanged'|'locked'|'conflict'
     */
    public function point(ProvenanceWriter $p, Facility $facility, string $column, FacilityMedia $media, ?int $recordId, string $label): string
    {
        $previous = $facility->getAttribute($column);
        $result = $p->from($media->source_url)->scalar($facility, $column, $media->path, false, $recordId, $label);

        if ($result === 'written' && $previous !== null) {
            FacilityMedia::query()->where('facility_id', $facility->getKey())->where('path', $previous)
                ->update(['path' => null, 'status' => FacilityMedia::STATUS_REPLACED, 'updated_at' => now()]);
        }

        return $result;
    }

    /**
     * An http(s) address, whitespace collapsed, at most 255 characters (as
     * the website import has always stored them).
     */
    public static function url(mixed $value): ?string
    {
        $value = is_scalar($value) ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $value)), 0, 255) : '';

        return $value !== '' && preg_match('#^https?://#i', $value) === 1 ? $value : null;
    }
}
