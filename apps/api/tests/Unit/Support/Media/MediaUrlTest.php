<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\MediaUrl;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // MediaUrl now asks the disk for its URL, so the disk config is what matters
        // here — not app.url, which the public disk only reads at config-load time.
        config([
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
