<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_store_branding_rejects_an_unsupported_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('payload.html', '<h1>hi</h1>', 'text/html');

        $this->expectExceptionMessage('Unsupported branding file type.');

        app(ImageOptimizer::class)->storeBranding($file, 'site/logo');
    }
}
