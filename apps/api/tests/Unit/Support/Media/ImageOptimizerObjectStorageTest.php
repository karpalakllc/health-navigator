<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\ImageOptimizer;
use Aws\CommandInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\Support\FakeS3MediaDisk;
use Tests\TestCase;

class ImageOptimizerObjectStorageTest extends TestCase
{
    private FakeS3MediaDisk $s3;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'media.visibility' => 'public',
            'media.cache_control' => 'public, max-age=31536000, immutable',
        ]);

        $this->s3 = FakeS3MediaDisk::install();
    }

    private function onlyPut(): CommandInterface
    {
        $puts = $this->s3->commands('PutObject');
        $this->assertCount(1, $puts);

        return $puts[0];
    }

    public function test_optimised_images_are_written_public_as_webp_with_an_immutable_cache_header(): void
    {
        $path = app(ImageOptimizer::class)->store(UploadedFile::fake()->image('a.jpg', 40, 40), 'users/avatars');

        $put = $this->onlyPut();
        $this->assertSame('zdravje-media', $put['Bucket']);
        $this->assertSame($path, $put['Key']);
        $this->assertMatchesRegularExpression('#^media/users/avatars/[0-9a-f-]{36}\.webp$#', $path);
        $this->assertSame('public-read', $put['ACL']);
        $this->assertSame('image/webp', $put['ContentType']);
        $this->assertSame('inline', $put['ContentDisposition']);
        $this->assertSame('public, max-age=31536000, immutable', $put['CacheControl']);
    }

    public function test_svg_branding_is_served_as_inline_svg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg, 'image/svg+xml');

        $path = app(ImageOptimizer::class)->storeBranding($file, 'site/logo');

        $put = $this->onlyPut();
        $this->assertStringEndsWith('.svg', $path);
        $this->assertSame('image/svg+xml', $put['ContentType']);
        $this->assertSame('inline', $put['ContentDisposition']);
        $this->assertSame('public-read', $put['ACL']);
        $this->assertSame('public, max-age=31536000, immutable', $put['CacheControl']);
    }

    public function test_raster_branding_keeps_its_own_content_type(): void
    {
        app(ImageOptimizer::class)->storeBranding(UploadedFile::fake()->image('favicon.png', 32, 32), 'site/favicon');

        $this->assertSame('image/png', $this->onlyPut()['ContentType']);
    }

    /** Buckets that refuse object ACLs get public read from a bucket policy instead. */
    public function test_media_visibility_can_be_private_for_buckets_without_acls(): void
    {
        config(['media.visibility' => 'private']);

        app(ImageOptimizer::class)->store(UploadedFile::fake()->image('a.jpg', 40, 40), 'users/avatars');

        $this->assertSame('private', $this->onlyPut()['ACL']);
    }

    /**
     * The disk has `throw` off, so a rejected upload is only a `false` return —
     * previously ignored, handing back the path of a file that was never stored.
     */
    public function test_a_failed_upload_is_an_error_not_a_dangling_path(): void
    {
        $this->s3->failWrites();

        $this->expectException(RuntimeException::class);

        app(ImageOptimizer::class)->store(UploadedFile::fake()->image('a.jpg', 40, 40), 'users/avatars');
    }

    public function test_delete_removes_the_object_from_the_bucket(): void
    {
        app(ImageOptimizer::class)->delete('media/users/avatars/old.webp');

        $deletes = $this->s3->commands('DeleteObject');
        $this->assertCount(1, $deletes);
        $this->assertSame('media/users/avatars/old.webp', $deletes[0]['Key']);
    }
}
