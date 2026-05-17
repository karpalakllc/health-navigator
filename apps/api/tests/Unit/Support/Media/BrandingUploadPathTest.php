<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\BrandingUploadPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrandingUploadPathTest extends TestCase
{
    public function test_normalizes_storage_path_string(): void
    {
        $this->assertSame(
            'media/site/logo/example.svg',
            BrandingUploadPath::normalize('media/site/logo/example.svg'),
        );
    }

    public function test_normalizes_corrupt_json_from_filament(): void
    {
        $this->assertNull(
            BrandingUploadPath::normalize('{"0a0d230f-fe59-4506-828a-6cd217508318":{}}'),
        );
        $this->assertTrue(
            BrandingUploadPath::isCorruptState('{"0a0d230f-fe59-4506-828a-6cd217508318":{}}'),
        );
    }

    #[DataProvider('arrayStateProvider')]
    public function test_normalizes_array_state(mixed $state, ?string $expected): void
    {
        $this->assertSame($expected, BrandingUploadPath::normalize($state));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function arrayStateProvider(): array
    {
        return [
            'path list' => [['media/site/logo/a.svg'], 'media/site/logo/a.svg'],
            'uuid map' => [['7992ab47-349a-42d9-9e39-41fd159f3c74' => []], null],
        ];
    }
}
