<?php

namespace Tests\Unit\Support\Media;

use App\Support\Media\NameInitials;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NameInitialsTest extends TestCase
{
    #[DataProvider('namesProvider')]
    public function test_from_name(string $name, string $expected): void
    {
        $this->assertSame($expected, NameInitials::from($name));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function namesProvider(): array
    {
        return [
            'two parts' => ['Martin Ilievski', 'MI'],
            'single name' => ['Madonna', 'MA'],
            'three parts' => ['Ana Maria Petrova', 'AP'],
        ];
    }
}
