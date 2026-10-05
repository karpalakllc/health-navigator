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
     *
     * `use` and `image` are here because their href/xlink:href can reference an
     * external document, and `style` because CSS can pull remote resources — the
     * comment previously claimed `use` was covered when it was not.
     *
     * @var list<string>
     */
    private const DISALLOWED_SVG_ELEMENTS = [
        'script', 'foreignObject', 'iframe', 'embed', 'object',
        'animate', 'animateTransform', 'set', 'handler',
        'use', 'image', 'style',
    ];

    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    private const XSLT_NAMESPACE = 'http://www.w3.org/1999/XSL/Transform';

    /**
     * Editor-state namespaces (Inkscape's default "Inkscape SVG" export). Their
     * elements and attributes never render and are removed before validation.
     *
     * @var list<string>
     */
    private const EDITOR_NAMESPACES = [
        'http://www.inkscape.org/namespaces/inkscape',
        'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd',
    ];

    /**
     * Licence/authoring vocabularies Inkscape writes inside <metadata>. Only
     * their now-unused declarations are dropped: <metadata> goes whole, and an
     * element in one of these namespaces anywhere else is still refused.
     *
     * @var list<string>
     */
    private const METADATA_NAMESPACES = [
        'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'http://creativecommons.org/ns#',
        'http://web.resource.org/cc/',
        'http://purl.org/dc/elements/1.1/',
    ];

    /**
     * Decoded GD images cost ~4 bytes per pixel, so 24 MP is ~96 MB — enough for
     * a modern phone photo, well short of what a crafted header can claim.
     */
    public const MAX_TOTAL_PIXELS = 24_000_000;

    public const MAX_SIDE_PIXELS = 10_000;

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
            // The cleaned, validated document is what gets stored — never the
            // uploaded bytes.
            return $this->storeContents($this->sanitisedSvg($file), $subdirectory, $extension);
        }

        if ($extension !== 'ico') {
            // Raster branding is stored verbatim, so confirm it really decodes —
            // and is not a decompression bomb every visitor's browser must decode.
            // ICO is excluded because GD cannot read it.
            $this->assertWithinPixelBudget((string) $file->getRealPath());
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

    private function loadImage(UploadedFile $file): \GdImage
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new InvalidImageException('Could not read uploaded image.');
        }

        // Read the header before GD decodes anything: a small, highly
        // compressible file can declare enough pixels to exhaust memory.
        $this->assertWithinPixelBudget($path);

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw new InvalidImageException('Unsupported or corrupt image file.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * getimagesize() only parses the header, so this is cheap even for a bomb.
     */
    private function assertWithinPixelBudget(string $path): void
    {
        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new InvalidImageException('Unsupported or corrupt image file.');
        }

        if (
            $size[0] > self::MAX_SIDE_PIXELS
            || $size[1] > self::MAX_SIDE_PIXELS
            || $size[0] * $size[1] > self::MAX_TOTAL_PIXELS
        ) {
            throw new InvalidImageException('Image dimensions are too large.');
        }
    }

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
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        return $this->storeContents($contents, $subdirectory, $extension);
    }

    private function storeContents(string $contents, string $subdirectory, string $extension): string
    {
        $path = $this->buildPath($subdirectory, $extension);

        Storage::disk(config('media.disk'))->put($path, $contents, [
            'visibility' => 'public',
        ]);

        return $path;
    }

    /**
     * SVG is the one branding format that is executable. It is stored on a
     * public disk served from the API origin — the same origin as the admin panel —
     * so a script inside one runs there when the file is opened directly.
     *
     * The previous check (MIME plus a 500-byte substring sniff) was trivially
     * defeated: 500 bytes of comments followed by a <script> element passed. This
     * parses the document and rejects active content outright rather than trying to
     * sanitise it, which is the safer direction for an upload we do not need to be
     * clever about.
     *
     * The one thing removed rather than rejected is editor metadata (see
     * stripEditorMetadata()), so an Inkscape export uploads as it is. Removal
     * only ever deletes nodes, and the result is serialised, parsed again and
     * validated in full: the bytes returned — and stored — are exactly the bytes
     * that passed validation.
     */
    private function sanitisedSvg(UploadedFile $file): string
    {
        if ($file->getMimeType() !== 'image/svg+xml') {
            throw new RuntimeException('Invalid SVG file.');
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Invalid SVG file.');
        }

        $document = $this->parseSvg((string) file_get_contents($path));
        $this->stripEditorMetadata($document);

        $cleaned = $document->saveXML();

        if ($cleaned === false) {
            throw new RuntimeException('Invalid SVG file.');
        }

        $this->assertSvgIsInert($this->parseSvg($cleaned));

        return $cleaned;
    }

    private function parseSvg(string $contents): \DOMDocument
    {
        // A DTD can declare internal entities whose replacement text is markup
        // (<script>, onload=…). XPath never sees inside an unexpanded entity, but
        // a browser rendering the file does expand it. A logo needs no DTD.
        if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            throw new RuntimeException('SVG contains a DOCTYPE or entity declaration.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        // LIBXML_NONET blocks external entity fetches. LIBXML_NOENT is deliberately
        // NOT passed — it would enable entity substitution (XXE, billion laughs).
        $parsed = $document->loadXML($contents, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $parsed || $document->documentElement?->localName !== 'svg') {
            throw new RuntimeException('Invalid SVG file.');
        }

        // Belt and braces for the textual check above (e.g. odd encodings).
        if ($document->doctype !== null) {
            throw new RuntimeException('SVG contains a DOCTYPE or entity declaration.');
        }

        return $document;
    }

    /**
     * Inkscape's default export keeps its editor state in the file: a
     * sodipodi:namedview element, inkscape:* / sodipodi:* attributes and
     * elements, and an RDF licence block in <metadata>. None of it renders, so
     * it is deleted — along with comments — instead of failing the namespace
     * allow-list. Whatever is nested inside a deleted element goes with it.
     * Nothing is renamed or rewritten, so this cannot turn an inert node into
     * an active one; validation still runs on the result.
     */
    private function stripEditorMetadata(\DOMDocument $document): void
    {
        $xpath = $this->xpath($document);

        $doomed = [];

        foreach ($xpath->query('//*') ?: [] as $element) {
            $isEditorElement = in_array($element->namespaceURI, self::EDITOR_NAMESPACES, true);
            $isMetadata = $element->localName === 'metadata'
                && in_array($element->namespaceURI, [null, self::SVG_NAMESPACE], true);

            if ($isEditorElement || $isMetadata) {
                $doomed[] = $element;
            }
        }

        foreach ($xpath->query('//comment()') ?: [] as $comment) {
            $doomed[] = $comment;
        }

        foreach ($doomed as $node) {
            $node->parentNode?->removeChild($node);
        }

        $attributes = [];

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            if (in_array($attribute->namespaceURI, self::EDITOR_NAMESPACES, true)) {
                $attributes[] = $attribute;
            }
        }

        foreach ($attributes as $attribute) {
            $attribute->ownerElement?->removeAttributeNode($attribute);
        }

        // Drop the declarations nothing uses any more, so the stored file carries
        // no trace of the editor. Only unused ones: removing the declaration of a
        // namespace still in use (rdf:RDF outside <metadata>) would leave libxml
        // to re-home that element, possibly into the SVG namespace — a rename.
        // Such an element is left as it is, for the allow-list to refuse.
        $inUse = [];

        foreach ($xpath->query('//* | //@*') ?: [] as $node) {
            $inUse[(string) $node->namespaceURI] = true;
        }

        $unused = array_values(array_filter(
            array_merge(self::EDITOR_NAMESPACES, self::METADATA_NAMESPACES),
            fn (string $uri): bool => ! isset($inUse[$uri]),
        ));

        foreach ($xpath->query('//*') ?: [] as $element) {
            foreach ($xpath->query('namespace::*', $element) ?: [] as $namespace) {
                if (in_array($namespace->nodeValue, $unused, true)) {
                    $element->removeAttributeNS((string) $namespace->nodeValue, (string) $namespace->localName);
                }
            }
        }
    }

    private function assertSvgIsInert(\DOMDocument $document): void
    {
        $xpath = $this->xpath($document);

        // An xml-stylesheet PI with type="text/xsl" makes the browser run an XSLT (one
        // can be embedded in the file itself) and render its output, which may
        // contain a <script>. The XML declaration is not a PI node, so a logo
        // has no reason to carry any.
        if ($xpath->query('//processing-instruction()')?->length > 0) {
            throw new RuntimeException('SVG contains processing instructions.');
        }

        foreach ($xpath->query('//namespace::*') ?: [] as $namespace) {
            if (rtrim((string) $namespace->nodeValue, '/') === rtrim(self::XSLT_NAMESPACE, '/')) {
                throw new RuntimeException('SVG contains disallowed namespaces.');
            }
        }

        // Namespace allow-list: element and attribute deny-lists only know SVG's
        // own names, so xsl:element, xhtml:script or XML Events attributes would
        // slip past them. No namespace is inert and stays allowed.
        foreach ($xpath->query('//*') ?: [] as $element) {
            if (! in_array($element->namespaceURI, [null, self::SVG_NAMESPACE], true)) {
                throw new RuntimeException('SVG contains disallowed namespaces.');
            }
        }

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            if (! in_array($attribute->namespaceURI, [null, self::XLINK_NAMESPACE, self::XML_NAMESPACE], true)) {
                throw new RuntimeException('SVG contains disallowed namespaces.');
            }
        }

        foreach (self::DISALLOWED_SVG_ELEMENTS as $tag) {
            if ($xpath->query('//*[local-name()="'.$tag.'"]')?->length > 0) {
                throw new RuntimeException('SVG contains disallowed elements.');
            }
        }

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            $localName = strtolower((string) $attribute->localName);
            $value = self::normaliseAttributeValue((string) $attribute->nodeValue);

            if (str_starts_with($localName, 'on')) {
                throw new RuntimeException('SVG contains event handlers.');
            }

            // Links may only point inside the document (gradients, clip paths).
            // An allow-list, because scheme deny-lists are what got bypassed.
            if ($localName === 'href' && ! preg_match('/^#[a-z0-9_.:-]*$/', $value)) {
                throw new RuntimeException('SVG contains disallowed references.');
            }

            if (
                str_contains($value, 'javascript:')
                || str_contains($value, 'vbscript:')
                || str_contains($value, 'data:text/html')
            ) {
                throw new RuntimeException('SVG contains disallowed references.');
            }

            // fill="url(#grad)" is fine; url() to anything else (also inside a
            // style attribute) fetches or embeds foreign content.
            if (preg_match_all('/url\(([^)]*)\)/', $value, $urls) > 0) {
                foreach ($urls[1] as $url) {
                    if (! str_starts_with(trim($url, '\'"'), '#')) {
                        throw new RuntimeException('SVG contains disallowed references.');
                    }
                }
            }
        }
    }

    /**
     * Every SVG pass queries through this, so a test can count what a large
     * file costs.
     */
    protected function xpath(\DOMDocument $document): \DOMXPath
    {
        return new \DOMXPath($document);
    }

    /**
     * Browsers ignore ASCII whitespace and control characters inside a URL
     * scheme, so `java&#9;script:` runs as `javascript:`. The parser has already
     * decoded character references into nodeValue; decode any HTML-style ones
     * left over, then drop everything a browser would ignore before comparing.
     */
    private static function normaliseAttributeValue(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strtolower((string) preg_replace('/[\x00-\x20\x7F]+/u', '', $decoded));
    }

    private function buildPath(string $subdirectory, string $extension = 'webp'): string
    {
        $base = trim((string) config('media.directory'), '/');
        $folder = trim($subdirectory, '/');

        return $base.'/'.$folder.'/'.Str::uuid()->toString().'.'.$extension;
    }
}
