<?php

namespace Database\Seeders\Concerns;

use App\Models\Doctor;
use App\Models\Facility;
use App\Support\DeploymentEnvironment;
use App\Support\Media\ImageOptimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Demo photos (database/seeders/assets/unsplash, see CREDITS.md there) copied
 * onto the media disk through the upload pipeline, so their URLs behave like
 * admin uploads. They are stock photos of real people next to fictional
 * names: never attached in a deployed environment, SEED_LOCAL_DEMO or not.
 *
 * Files land under media/demo/, which holds nothing else, so a re-seed can
 * drop the copies no row points at any more (e.g. after migrate:fresh).
 */
trait AttachesDemoImages
{
    protected function shouldAttachDemoImages(): bool
    {
        return ! DeploymentEnvironment::isDeployed();
    }

    protected static function demoImageAssetPath(string $asset): string
    {
        return database_path('seeders/assets/unsplash/'.ltrim($asset, '/'));
    }

    /**
     * Idempotent: a row already showing a demo copy keeps it.
     */
    protected function attachDemoImage(
        Model $model,
        string $column,
        string $asset,
        string $subdirectory,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
    ): void {
        $source = self::demoImageAssetPath($asset);

        if (! is_file($source)) {
            $this->command?->warn("Demo image missing: {$asset}");

            return;
        }

        $current = $model->getAttribute($column);
        $disk = Storage::disk(config('media.disk'));

        if (is_string($current) && str_starts_with($current, $this->demoMediaPrefix()) && $disk->exists($current)) {
            return;
        }

        $path = app(ImageOptimizer::class)->store(
            new UploadedFile($source, basename($source), null, null, true),
            'demo/'.$subdirectory,
            $maxWidth,
            $maxHeight,
        );

        // A replaced media path is deleted by DeletesReplacedMedia.
        $model->update([$column => $path]);
    }

    protected function pruneUnusedDemoImages(): void
    {
        $prefix = $this->demoMediaPrefix();
        $disk = Storage::disk(config('media.disk'));

        $referenced = collect()
            ->merge(Doctor::withTrashed()->where('avatar_url', 'like', $prefix.'%')->pluck('avatar_url'))
            ->merge(Facility::withTrashed()->where('avatar_url', 'like', $prefix.'%')->pluck('avatar_url'))
            ->merge(Facility::withTrashed()->where('cover_path', 'like', $prefix.'%')->pluck('cover_path'))
            ->flip();

        foreach ($disk->allFiles(rtrim($prefix, '/')) as $file) {
            if (! $referenced->has($file)) {
                $disk->delete($file);
            }
        }
    }

    private function demoMediaPrefix(): string
    {
        return trim((string) config('media.directory'), '/').'/demo/';
    }
}
