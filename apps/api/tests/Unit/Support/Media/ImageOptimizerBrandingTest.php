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
}
