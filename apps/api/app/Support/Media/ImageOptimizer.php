<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageOptimizer
{
    /**
     * Elements that can execute, fetch, or embed foreign content inside an SVG.
     * `use` is included because xlink:href can reference an external document.
     *
     * @var list<string>
     */
    private const DISALLOWED_SVG_ELEMENTS = [
        'script', 'foreignObject', 'iframe', 'embed', 'object',
        'animate', 'animateTransform', 'set', 'handler',
    ];

    /**
     * Store logo/favicon and other branding assets in their original format (SVG stays SVG).
     */
    public function storeBranding(UploadedFile $file, string $subdirectory): string
    {
        // Derive the extension from the detected MIME type, not from the client's
        // filename — the old code trusted getClientOriginalExtension(), which the
        // uploader controls.
        $extension = match ($file->getMimeType()) {
            'image/svg+xml' => 'svg',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => throw new RuntimeException('Unsupported branding file type.'),
        };

        if ($extension === 'svg') {
            $this->assertSvg($file);
        } elseif ($extension !== 'ico' && @getimagesize((string) $file->getRealPath()) === false) {
            // Raster branding is stored verbatim, so confirm it really decodes.
            // ICO is excluded because GD cannot read it.
            throw new RuntimeException('Unsupported or corrupt image file.');
        }

        return $this->storeRaw($file, $subdirectory, $extension);
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

    /**
     * SVG is the one branding format that is executable. It is stored verbatim on a
     * public disk served from the API origin — the same origin as the admin panel —
     * so a script inside one runs there when the file is opened directly.
     *
     * The previous check (MIME plus a 500-byte substring sniff) was trivially
     * defeated: 500 bytes of comments followed by a <script> element passed. This
     * parses the document and rejects active content outright rather than trying to
     * sanitise it, which is the safer direction for an upload we do not need to be
     * clever about.
     */
    private function assertSvg(UploadedFile $file): void
    {
        if ($file->getMimeType() !== 'image/svg+xml') {
            throw new RuntimeException('Invalid SVG file.');
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Invalid SVG file.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        // LIBXML_NONET blocks external entity fetches. LIBXML_NOENT is deliberately
        // NOT passed — it would enable entity substitution (XXE, billion laughs).
        $parsed = $document->loadXML((string) file_get_contents($path), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $parsed || $document->documentElement?->localName !== 'svg') {
            throw new RuntimeException('Invalid SVG file.');
        }

        $xpath = new \DOMXPath($document);

        foreach (self::DISALLOWED_SVG_ELEMENTS as $tag) {
            if ($xpath->query('//*[local-name()="'.$tag.'"]')?->length > 0) {
                throw new RuntimeException('SVG contains disallowed elements.');
            }
        }

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = strtolower(trim((string) $attribute->nodeValue));

            if (str_starts_with($name, 'on')) {
                throw new RuntimeException('SVG contains event handlers.');
            }

            if (str_contains($value, 'javascript:') || str_contains($value, 'data:text/html')) {
                throw new RuntimeException('SVG contains disallowed references.');
            }
        }
    }

    private function buildPath(string $subdirectory, string $extension = 'webp'): string
    {
        $base = trim((string) config('media.directory'), '/');
        $folder = trim($subdirectory, '/');

        return $base.'/'.$folder.'/'.Str::uuid()->toString().'.'.$extension;
    }
}
