<?php

namespace App\Models\Concerns;

use App\Support\Media\ImageOptimizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Media files belong to the row that points at them: when a column listed in
 * mediaPathColumns() is replaced or cleared, the old file is deleted, and a
 * force delete removes the current ones. Done on the model rather than in the
 * Filament upload's delete callback, which fires the moment the file is
 * removed in the form — before (and even without) the record being saved.
 *
 * Only paths under the media directory are touched: legacy rows hold external
 * URLs (ImageOptimizer::delete() skips those too) and nothing else on the
 * disk belongs to a record. Deletion waits for the surrounding transaction to
 * commit, so a rolled-back save keeps its file.
 */
trait DeletesReplacedMedia
{
    /**
     * @return list<string>
     */
    abstract protected function mediaPathColumns(): array;

    public static function bootDeletesReplacedMedia(): void
    {
        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $model->deleteMediaFiles(array_map(
                fn (string $column) => $model->getOriginal($column),
                array_filter($model->mediaPathColumns(), fn (string $column) => $model->wasChanged($column)),
            ));
        });

        static::forceDeleted(function (Model $model): void {
            /** @var Model&self $model */
            $model->deleteMediaFiles(array_map(
                fn (string $column) => $model->getAttribute($column),
                $model->mediaPathColumns(),
            ));
        });
    }

    /**
     * @param  array<int, mixed>  $paths
     */
    protected function deleteMediaFiles(array $paths): void
    {
        $prefix = trim((string) config('media.directory'), '/').'/';

        $owned = array_values(array_filter(
            $paths,
            fn (mixed $path): bool => is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..'),
        ));

        if ($owned === []) {
            return;
        }

        $this->getConnection()->afterCommit(function () use ($owned): void {
            foreach ($owned as $path) {
                app(ImageOptimizer::class)->delete($path);
            }
        });
    }
}
