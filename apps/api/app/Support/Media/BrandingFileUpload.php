<?php

namespace App\Support\Media;

use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BrandingFileUpload
{
    /**
     * Move Livewire temp uploads to permanent branding storage.
     *
     * @param  mixed  $state
     * @return list<string>|null  Replacement state when temp files were stored; null if unchanged.
     */
    public static function persistTemporaryFiles(mixed $state, string $subdirectory): ?array
    {
        if (blank($state)) {
            return null;
        }

        $paths = [];
        $changed = false;

        foreach (Arr::wrap($state) as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $paths[] = app(ImageOptimizer::class)->storeBranding($file, $subdirectory);
                $changed = true;
            } elseif (is_string($file) && str_contains($file, 'media/')) {
                $paths[] = $file;
            }
        }

        if (! $changed || $paths === []) {
            return null;
        }

        $unique = array_values(array_unique($paths));

        return [$unique[array_key_last($unique)]];
    }
}
