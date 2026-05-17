<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageOptimizer
{
    /**
     * Store logo/favicon and other branding assets in their original format (SVG stays SVG).
     */
    public function storeBranding(UploadedFile $file, string $subdirectory): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        $allowed = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico'];

        if (! in_array($extension, $allowed, true)) {
            throw new RuntimeException('Unsupported branding file type.');
        }

        if ($extension === 'svg') {
            $this->assertSvg($file);
        }

        return $this->storeRaw($file, $subdirectory, $extension === 'jpeg' ? 'jpg' : $extension);
    }

    public function store(UploadedFile $file, string $subdirectory, ?int $maxWidth = null, ?int $maxHeight = null): string
    {
        $image = $this->loadImage($file);

        try {
            $resized = $this->resize(
                $image,
                $maxWidth ?? (int) config('media.max_width'),
                $maxHeight ?? (int) config('media.max_height'),
            );

            if ($resized !== $image) {
                imagedestroy($image);
                $image = $resized;
            }

            $binary = $this->encodeWebp($image);
        } finally {
            imagedestroy($image);
        }

        $path = $this->buildPath($subdirectory);
        Storage::disk(config('media.disk'))->put($path, $binary, [
            'visibility' => 'public',
        ]);

        return $path;
    }

    public function delete(?string $pathOrUrl): void
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return;
        }

        Storage::disk(config('media.disk'))->delete($pathOrUrl);
    }

    /**
     * @return \GdImage
     */
    private function loadImage(UploadedFile $file): \GdImage
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Could not read uploaded image.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw new RuntimeException('Unsupported or corrupt image file.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * @return \GdImage
     */
    private function resize(\GdImage $image, int $maxWidth, int $maxHeight): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $image;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('Could not resize image.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function encodeWebp(\GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, (int) config('media.webp_quality'));
        $binary = ob_get_clean();

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Could not encode image as WebP.');
        }

        return $binary;
    }

    private function storeRaw(UploadedFile $file, string $subdirectory, string $extension): string
    {
        $path = $this->buildPath($subdirectory, $extension);
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        Storage::disk(config('media.disk'))->put($path, $contents, [
            'visibility' => 'public',
        ]);

        return $path;
    }

    private function assertSvg(UploadedFile $file): void
    {
        if ($file->getMimeType() !== 'image/svg+xml') {
            throw new RuntimeException('Invalid SVG file.');
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Invalid SVG file.');
        }

        $snippet = strtolower(substr((string) file_get_contents($path), 0, 500));

        if (! str_contains($snippet, '<svg') && ! str_contains($snippet, '<?xml')) {
            throw new RuntimeException('Invalid SVG file.');
        }
    }

    private function buildPath(string $subdirectory, string $extension = 'webp'): string
    {
        $base = trim((string) config('media.directory'), '/');
        $folder = trim($subdirectory, '/');

        return $base.'/'.$folder.'/'.Str::uuid()->toString().'.'.$extension;
    }
}
