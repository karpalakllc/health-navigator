<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ImageOptimizerBrandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.disk' => 'public']);
    }

    public function test_store_branding_keeps_svg_extension(): void
    {
        $svg = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>
            SVG;

        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $path = app(ImageOptimizer::class)->storeBranding($file, 'site/logo');

        $this->assertStringEndsWith('.svg', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('<svg', Storage::disk('public')->get($path));
    }

    public function test_store_branding_keeps_png_extension(): void
    {
        $file = UploadedFile::fake()->image('favicon.png', 32, 32);

        $path = app(ImageOptimizer::class)->storeBranding($file, 'site/favicon');

        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * Branding SVGs are served from the API origin, which is also the admin
     * panel's origin — so an executable one is stored XSS against staff.
     */
    public function test_store_branding_rejects_svg_containing_a_script(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $this->expectExceptionMessage('SVG contains disallowed elements.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    public function test_store_branding_rejects_svg_with_an_event_handler(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="1"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $this->expectExceptionMessage('SVG contains event handlers.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    public function test_store_branding_rejects_svg_hidden_behind_leading_padding(): void
    {
        // The previous check only sniffed the first 500 bytes, so padding defeated it.
        $svg = '<!--'.str_repeat(' ', 600).'-->'
            .'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $this->expectExceptionMessage('SVG contains disallowed elements.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    public function test_store_branding_rejects_a_non_image_masquerading_as_png(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'logo.png',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'image/png',
        );

        $this->expectExceptionMessage('Unsupported or corrupt image file.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function svgBypassPayloads(): array
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';

        return [
            // XPath never sees inside an unexpanded entity; a browser expands it.
            'internal entity carrying a script' => [
                '<!DOCTYPE svg [<!ENTITY x "<script>alert(1)</script>">]>'
                ."<svg {$ns}>&x;</svg>",
            ],
            'internal entity carrying an event handler' => [
                '<!DOCTYPE svg [<!ENTITY x "<g onload=\'alert(1)\'/>">]>'
                ."<svg {$ns}>&x;</svg>",
            ],
            'bare doctype' => [
                '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
                ."<svg {$ns}/>",
            ],
            'tab inside the javascript scheme' => [
                "<svg {$ns}><a href=\"java&#9;script:alert(1)\"><circle r=\"1\"/></a></svg>",
            ],
            'newline and case inside the javascript scheme' => [
                "<svg {$ns}><a xlink:href=\" JaVa&#10;ScRiPt:alert(1)\"><circle r=\"1\"/></a></svg>",
            ],
            'external href' => [
                "<svg {$ns}><a href=\"https://evil.example/\"><circle r=\"1\"/></a></svg>",
            ],
            'data uri href' => [
                "<svg {$ns}><a href=\"data:image/svg+xml;base64,PHN2Zy8+\"><circle r=\"1\"/></a></svg>",
            ],
            'external url() in a style attribute' => [
                "<svg {$ns}><rect style=\"fill:url(https://evil.example/x.svg#a)\"/></svg>",
            ],
            // The browser applies an XSLT stylesheet PI and renders its output,
            // which can be a <script>; the stylesheet itself lives in the file.
            'xml-stylesheet PI with an embedded XSLT' => [
                '<?xml version="1.0"?>'
                .'<?xml-stylesheet type="text/xsl" href="#s"?>'
                ."<svg {$ns} xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\">"
                .'<xsl:stylesheet id="s" version="1.0"><xsl:template match="/">'
                .'<xsl:element name="script">alert(1)</xsl:element>'
                .'</xsl:template></xsl:stylesheet></svg>',
            ],
            'xsl namespace on the root svg with xsl:element' => [
                "<svg {$ns} xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\">"
                .'<xsl:element name="script">alert(1)</xsl:element></svg>',
            ],
            'xsl namespace declared but unused' => [
                "<svg {$ns} xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\"><circle r=\"1\"/></svg>",
            ],
            'any other processing instruction' => [
                "<?foo bar?><svg {$ns}><circle r=\"1\"/></svg>",
            ],
            'xhtml element' => [
                "<svg {$ns}><h:div xmlns:h=\"http://www.w3.org/1999/xhtml\">x</h:div></svg>",
            ],
            'foreign-namespace attribute' => [
                "<svg {$ns} xmlns:ev=\"http://www.w3.org/2001/xml-events\"><circle ev:event=\"click\" r=\"1\"/></svg>",
            ],
        ];
    }

    #[DataProvider('svgBypassPayloads')]
    public function test_store_branding_rejects_svg_bypass_payloads(string $svg): void
    {
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^SVG contains /');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    public function test_store_branding_still_accepts_internal_references(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">'
            .'<defs><linearGradient id="g"><stop offset="0"/></linearGradient></defs>'
            .'<rect width="10" height="10" fill="url(#g)"/>'
            .'<a xlink:href="#g"><circle cx="5" cy="5" r="4"/></a>'
            .'</svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $path = app(ImageOptimizer::class)->storeBranding($file, 'site/logo');

        Storage::disk('public')->assertExists($path);
    }

    public function test_store_branding_accepts_a_typical_exported_logo(): void
    {
        $svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
            .'<!-- Generator: an editor -->'
            .'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            .'version="1.1" viewBox="0 0 20 20" xml:space="preserve">'
            .'<title>Logo</title><desc>Brand mark</desc>'
            .'<defs><radialGradient id="r" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fff"/></radialGradient>'
            .'<clipPath id="c"><rect width="20" height="20"/></clipPath>'
            .'<linearGradient id="l" xlink:href="#r"/></defs>'
            .'<g clip-path="url(#c)"><path d="M0 0h20v20z" fill="url(\'#l\')"/><text x="1" y="15">Z</text></g>'
            .'</svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $path = app(ImageOptimizer::class)->storeBranding($file, 'site/logo');

        Storage::disk('public')->assertExists($path);
    }

    private const INKSCAPE_LOGO = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
        .'<!-- Created with Inkscape (http://www.inkscape.org/) -->'
        .'<svg width="20mm" height="20mm" viewBox="0 0 20 20" version="1.1" id="svg5" '
        .'inkscape:version="1.3.2 (091e20e, 2023-11-25)" sodipodi:docname="logo.svg" '
        .'xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" '
        .'xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" '
        .'xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg" '
        .'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" '
        .'xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        .'<sodipodi:namedview id="namedview7" pagecolor="#ffffff" inkscape:zoom="4.2" '
        .'inkscape:current-layer="layer1"><inkscape:page x="0" y="0" width="20" height="20" id="page9"/>'
        .'</sodipodi:namedview>'
        .'<defs id="defs2"><inkscape:path-effect effect="spiro" id="path-effect1" is_visible="true"/>'
        .'<linearGradient inkscape:collect="always" id="g1"><stop offset="0" stop-color="#0a7"/></linearGradient></defs>'
        .'<metadata id="metadata1"><rdf:RDF><cc:Work rdf:about=""><dc:format>image/svg+xml</dc:format>'
        .'<dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage"/></cc:Work></rdf:RDF></metadata>'
        .'<g inkscape:label="Layer 1" inkscape:groupmode="layer" id="layer1">'
        .'<path style="fill:url(#g1);stroke:none" d="M 0,0 H 20 V 20 Z" id="path1" '
        .'sodipodi:nodetypes="cccc" inkscape:path-effect="#path-effect1"/></g></svg>';

    /**
     * Inkscape's default "Inkscape SVG" export carries editor state in its own
     * namespaces (sodipodi:namedview, inkscape:* attributes) and RDF licence
     * metadata — all non-rendering. It is stripped before validation, and what
     * is stored is the cleaned document, never the uploaded bytes.
     */
    public function test_store_branding_accepts_an_inkscape_export_and_stores_it_cleaned(): void
    {
        $file = UploadedFile::fake()->createWithContent('logo.svg', self::INKSCAPE_LOGO, 'image/svg+xml');

        $path = app(ImageOptimizer::class)->storeBranding($file, 'site/logo');

        $stored = Storage::disk('public')->get($path);
        $this->assertNotSame(self::INKSCAPE_LOGO, $stored);
        foreach (['inkscape', 'sodipodi', 'rdf', 'metadata', 'dc:format', 'creativecommons'] as $editorOnly) {
            $this->assertStringNotContainsStringIgnoringCase($editorOnly, $stored);
        }
        $this->assertStringContainsString('d="M 0,0 H 20 V 20 Z"', $stored);
        $this->assertStringContainsString('fill:url(#g1)', $stored);
        $this->assertStringContainsString('<linearGradient id="g1">', $stored);
    }

    /**
     * Whatever hides inside a stripped element is gone with it, and anything
     * active on what remains is still refused.
     */
    public function test_store_branding_strips_content_hidden_in_editor_metadata(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" '
            .'xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd">'
            .'<metadata><script>alert(1)</script><h:b xmlns:h="http://www.w3.org/1999/xhtml">x</h:b></metadata>'
            .'<sodipodi:namedview><script>alert(2)</script></sodipodi:namedview>'
            .'<circle r="1"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $stored = Storage::disk('public')->get(app(ImageOptimizer::class)->storeBranding($file, 'site/logo'));

        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('alert', $stored);
        $this->assertStringContainsString('<circle r="1"/>', $stored);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function editorNamespacesThatStayRefused(): array
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" '
            .'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"';

        return [
            // RDF is only stripped inside <metadata>.
            'rdf outside metadata' => ["<svg {$ns}><rdf:RDF/><circle r=\"1\"/></svg>"],
            // Stripping the editor attribute leaves the handler, which is refused.
            'event handler beside an inkscape attribute' => ["<svg {$ns}><g inkscape:label=\"x\" onload=\"alert(1)\"/></svg>"],
            'script beside an inkscape element' => ["<svg {$ns}><inkscape:page/><script>alert(1)</script></svg>"],
        ];
    }

    #[DataProvider('editorNamespacesThatStayRefused')]
    public function test_store_branding_still_refuses_active_content_around_editor_metadata(string $svg): void
    {
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^SVG contains /');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    public function test_store_branding_rejects_a_raster_decompression_bomb(): void
    {
        $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
        $file = UploadedFile::fake()->createWithContent('logo.png', $png, 'image/png');

        $this->expectExceptionMessage('Image dimensions are too large.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }

    public function test_store_branding_rejects_an_unsupported_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('payload.html', '<h1>hi</h1>', 'text/html');

        $this->expectExceptionMessage('Unsupported branding file type.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }
}
