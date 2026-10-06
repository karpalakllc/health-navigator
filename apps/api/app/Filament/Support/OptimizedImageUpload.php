<?php

namespace App\Filament\Support;

use App\Support\Media\BrandingFileUpload;
use App\Support\Media\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OptimizedImageUpload
{
    /** Wide header box; ImageOptimizer scales down to fit, never up or cropped. */
    public const COVER_MAX_WIDTH = 1600;

    public const COVER_MAX_HEIGHT = 900;

    /** Kilobytes, as FileUpload::maxSize() takes them. */
    public const COVER_MAX_KILOBYTES = 5120;

    /**
     * Replacing or removing the photo deletes the old file when the record is
     * saved (DeletesReplacedMedia), not when it is removed in the form.
     */
    public static function avatar(string $field = 'avatar_url', string $subdirectory = 'avatars'): FileUpload
    {
        return FileUpload::make($field)
            ->label('Photo')
            ->image()
            ->disk(config('media.disk'))
            ->directory(config('media.directory').'/'.$subdirectory)
            ->visibility('public')
            ->maxSize(5120)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->helperText('Uploaded images are compressed and saved as WebP.')
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) use ($subdirectory): string {
                return app(ImageOptimizer::class)->store($file, $subdirectory);
            });
    }

    /**
     * Facility/pharmacy header image: raster only, re-encoded to WebP and
     * scaled to fit 1600×900 through the same pipeline (and decompression-bomb
     * guard) as every other upload. The old file goes when the record is saved
     * (DeletesReplacedMedia).
     */
    public static function cover(string $field = 'cover_path', string $subdirectory = 'covers'): FileUpload
    {
        return FileUpload::make($field)
            ->label('Cover image')
            ->image()
            ->disk(config('media.disk'))
            ->directory(config('media.directory').'/'.$subdirectory)
            ->visibility('public')
            ->maxSize(self::COVER_MAX_KILOBYTES)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->helperText('Wide photo shown at the top of the profile. JPG, PNG or WebP up to 5 MB; saved as WebP, at most 1600×900.')
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) use ($subdirectory): string {
                return app(ImageOptimizer::class)->store(
                    $file,
                    $subdirectory,
                    self::COVER_MAX_WIDTH,
                    self::COVER_MAX_HEIGHT,
                );
            });
    }

    public static function siteBranding(string $field, string $subdirectory): FileUpload
    {
        $mediaRoot = trim((string) config('media.directory'), '/');

        return FileUpload::make($field)
            ->disk(config('media.disk'))
            ->directory($mediaRoot.'/'.$subdirectory)
            ->visibility('public')
            ->maxFiles(1)
            ->maxSize(2048)
            ->acceptedFileTypes([
                'image/svg+xml',
                'image/png',
                'image/jpeg',
                'image/webp',
                'image/gif',
                'image/x-icon',
                'image/vnd.microsoft.icon',
            ])
            ->previewable(false)
            ->helperText('SVG, PNG, or ICO are kept in original format (not converted to WebP).')
            ->afterStateUpdated(function ($state, FileUpload $component) use ($subdirectory): void {
                $stored = BrandingFileUpload::persistTemporaryFiles($state, $subdirectory);

                if ($stored !== null) {
                    $component->state($stored);
                }
            })
            ->deleteUploadedFileUsing(function (?string $file): void {
                app(ImageOptimizer::class)->delete($file);
            });
    }
}
