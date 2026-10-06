<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\MediaUrl;
use Tests\Support\FakeS3MediaDisk;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // MediaUrl now asks the disk for its URL, so the disk config is what matters
        // here — not app.url, which the public disk only reads at config-load time.
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'media.disk' => 'public',
            'filesystems.disks.public.url' => 'http://127.0.0.1:8000/storage',
        ]);
    }

    public function test_resolve_builds_url_from_storage_path(): void
    {
        $this->assertSame(
            'http://127.0.0.1:8000/storage/media/site/logo/test.svg',
            MediaUrl::resolve('media/site/logo/test.svg'),
        );
    }

    public function test_resolve_rewrites_localhost_storage_urls(): void
    {
        $this->assertSame(
            'http://127.0.0.1:8000/storage/media/site/logo/test.svg',
            MediaUrl::resolve('http://localhost/storage/media/site/logo/test.svg'),
        );
    }

    public function test_resolve_leaves_external_urls_untouched_including_query_string(): void
    {
        $external = 'https://api.dicebear.com/7.x/initials/svg?seed=ana';

        $this->assertSame($external, MediaUrl::resolve($external));
    }

    /**
     * Only our own /storage mount is legacy. A third-party host whose paths
     * happen to start with /storage/ used to be rewritten onto the API (or the
     * bucket), turning a working external image into a 404.
     */
    public function test_resolve_leaves_a_foreign_host_storage_url_untouched(): void
    {
        $foreign = 'https://images.example.org/storage/clinics/front.jpg';

        $this->assertSame($foreign, MediaUrl::resolve($foreign));
    }

    public function test_resolve_rebases_a_storage_url_on_the_api_host(): void
    {
        config(['app.url' => 'https://api.zdravje360.mk']);

        $this->assertSame(
            'http://127.0.0.1:8000/storage/media/site/logo/test.svg',
            MediaUrl::resolve('https://api.zdravje360.mk/storage/media/site/logo/test.svg'),
        );
    }

    public function test_resolve_builds_s3_urls_from_the_public_base_url(): void
    {
        FakeS3MediaDisk::install(['url' => 'https://media.zdravje360.mk/']);

        $this->assertSame(
            'https://media.zdravje360.mk/media/users/avatars/a.webp',
            MediaUrl::resolve('media/users/avatars/a.webp'),
        );
    }

    public function test_resolve_falls_back_to_the_bucket_object_url_without_a_public_base(): void
    {
        FakeS3MediaDisk::install();

        $this->assertSame(
            'https://zdravje-media.s3.eu-central-1.amazonaws.com/media/users/avatars/a.webp',
            MediaUrl::resolve('media/users/avatars/a.webp'),
        );
    }

    public function test_resolve_uses_a_path_style_endpoint_for_minio(): void
    {
        FakeS3MediaDisk::install([
            'endpoint' => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
        ]);

        $this->assertSame(
            'http://127.0.0.1:9000/zdravje-media/media/users/avatars/a.webp',
            MediaUrl::resolve('media/users/avatars/a.webp'),
        );
    }

    /** Rows written while media lived on the local disk keep working after the move. */
    public function test_resolve_rebases_legacy_local_storage_urls_onto_the_bucket(): void
    {
        FakeS3MediaDisk::install(['url' => 'https://media.zdravje360.mk']);

        $this->assertSame(
            'https://media.zdravje360.mk/media/site/logo/test.svg',
            MediaUrl::resolve('http://localhost/storage/media/site/logo/test.svg'),
        );
        $this->assertSame(
            'https://images.example.org/storage/x.jpg',
            MediaUrl::resolve('https://images.example.org/storage/x.jpg'),
        );
    }

    public function test_resolve_follows_the_configured_media_disk(): void
    {
        config([
            'media.disk' => 'cdn',
            'filesystems.disks.cdn' => [
                'driver' => 'local',
                'root' => storage_path('app/cdn'),
                'url' => 'https://cdn.example.com/assets',
            ],
        ]);

        $this->assertSame(
            'https://cdn.example.com/assets/media/site/logo/test.svg',
            MediaUrl::resolve('media/site/logo/test.svg'),
        );
    }

    public function test_resolve_returns_null_for_empty_values(): void
    {
        $this->assertNull(MediaUrl::resolve(null));
        $this->assertNull(MediaUrl::resolve(''));
    }
}
