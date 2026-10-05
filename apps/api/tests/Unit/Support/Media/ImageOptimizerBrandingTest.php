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
