<?php

namespace App\Filament\Support;

use App\Support\Media\BrandingFileUpload;
use App\Support\Media\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OptimizedImageUpload
{
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
            })
            ->deleteUploadedFileUsing(function (?string $file): void {
                app(ImageOptimizer::class)->delete($file);
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
