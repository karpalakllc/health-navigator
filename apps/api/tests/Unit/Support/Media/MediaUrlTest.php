<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\MediaUrl;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    public function test_resolve_builds_url_from_storage_path(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->assertSame(
            'http://127.0.0.1:8000/storage/media/site/logo/test.svg',
            MediaUrl::resolve('media/site/logo/test.svg'),
        );
    }

    public function test_resolve_rewrites_localhost_storage_urls(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->assertSame(
            'http://127.0.0.1:8000/storage/media/site/logo/test.svg',
            MediaUrl::resolve('http://localhost/storage/media/site/logo/test.svg'),
        );
    }
}
