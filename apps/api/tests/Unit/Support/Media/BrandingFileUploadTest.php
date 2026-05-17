<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\BrandingFileUpload;
use Tests\TestCase;

class BrandingFileUploadTest extends TestCase
{
    public function test_persist_temporary_files_returns_null_for_existing_paths(): void
    {
        $this->assertNull(
            BrandingFileUpload::persistTemporaryFiles(['media/site/logo/existing.svg'], 'site/logo'),
        );
    }

    public function test_persist_temporary_files_returns_null_for_empty_state(): void
    {
        $this->assertNull(BrandingFileUpload::persistTemporaryFiles([], 'site/logo'));
        $this->assertNull(BrandingFileUpload::persistTemporaryFiles(null, 'site/logo'));
    }
}
