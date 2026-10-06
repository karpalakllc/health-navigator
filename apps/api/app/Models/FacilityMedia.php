<?php

namespace App\Models;

use App\Support\Import\ProvenanceWriter;
use App\Support\Media\ImageOptimizer;
use App\Support\TaxonomyCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image taken from an institution's website: its logo (shown as the
 * facility avatar for identification, status "trademark-identification")
 * or a cover photo candidate (status "website"; the owner accepted
 * publishing these). The first cover candidate becomes the facility cover;
 * staff can switch to another or remove any image in one click.
 */
class FacilityMedia extends Model
{
    public const KIND_LOGO = 'logo';

    public const KIND_COVER = 'cover';

    public const STATUS_TRADEMARK = 'trademark-identification';

    public const STATUS_WEBSITE = 'website';

    public const STATUS_REMOVED = 'removed';

    /** A newer import replaced it while it was in use; its file is gone. */
    public const STATUS_REPLACED = 'replaced';

    protected $table = 'facility_media';

    protected $fillable = [
        'facility_id',
        'kind',
        'source',
        'source_url',
        'content_hash',
        'path',
        'status',
        'position',
        'removed_by_id',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'removed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function isRemoved(): bool
    {
        return $this->status === self::STATUS_REMOVED;
    }

    public function isCurrent(): bool
    {
        $facility = $this->facility;

        if ($facility === null || $this->path === null) {
            return false;
        }

        return $this->kind === self::KIND_LOGO
            ? $facility->avatar_url === $this->path
            : $facility->cover_path === $this->path;
    }

    /**
     * Use this cover candidate as the facility cover.
     */
    public function makeCurrentCover(?User $by): void
    {
        $facility = $this->facility;

        if ($this->kind !== self::KIND_COVER || $this->isRemoved() || $this->path === null || $facility === null
            || ! Storage::disk((string) config('media.disk'))->exists($this->path)) {
            return;
        }

        // Quietly: the previous cover is another candidate's file and must
        // survive, which DeletesReplacedMedia would not allow. The activity
        // entry below is the audit record.
        $facility->forceFill(['cover_path' => $this->path])->saveQuietly();
        TaxonomyCache::flush(...Facility::taxonomyCacheGroups());
        // A staff choice: re-imports must not switch it back.
        ProvenanceWriter::setLock($facility, 'cover_path', true, $by);

        activity('facility_media')
            ->performedOn($facility)
            ->causedBy($by)
            ->withProperties(['media_id' => $this->getKey(), 'source_url' => $this->source_url])
            ->log('cover switched');
    }

    /**
     * Takedown: delete the file, clear it from the facility if in use, keep
     * the row as "removed" so the next import does not bring it back.
     */
    public function remove(?User $by): void
    {
        if ($this->isRemoved()) {
            return;
        }

        $facility = $this->facility;
        $wasCurrent = $this->isCurrent();

        if ($wasCurrent && $facility !== null) {
            $column = $this->kind === self::KIND_LOGO ? 'avatar_url' : 'cover_path';
            $facility->forceFill([$column => null])->saveQuietly();
            TaxonomyCache::flush(...Facility::taxonomyCacheGroups());
            // A takedown means no image here, not "the next candidate".
            ProvenanceWriter::setLock($facility, $column, true, $by);
        }

        app(ImageOptimizer::class)->delete($this->path);

        $this->forceFill([
            'path' => null,
            'status' => self::STATUS_REMOVED,
            'removed_by_id' => $by?->getKey(),
            'removed_at' => now(),
        ])->save();

        activity('facility_media')
            ->performedOn($facility ?? $this)
            ->causedBy($by)
            ->withProperties(['media_id' => $this->getKey(), 'kind' => $this->kind, 'source_url' => $this->source_url, 'was_shown' => $wasCurrent])
            ->log('image removed');
    }
}
