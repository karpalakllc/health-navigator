<?php

namespace Tests\Unit;

use App\Support\MacedonianSearchVariants;
use PHPUnit\Framework\TestCase;

class MacedonianSearchVariantsTest extends TestCase
{
    public function test_latin_to_cyrillic_substring(): void
    {
        $this->assertSame('ана', MacedonianSearchVariants::latinToCyrillic('ana'));
        $this->assertSame('скопје', MacedonianSearchVariants::latinToCyrillic('skopje'));
    }

    public function test_cyrillic_to_latin_substring(): void
    {
        $this->assertSame('ana', MacedonianSearchVariants::cyrillicToLatin('ана'));
        $this->assertSame('skopje', MacedonianSearchVariants::cyrillicToLatin('скопје'));
    }

    public function test_variants_include_both_scripts(): void
    {
        $v = MacedonianSearchVariants::variants('ana');
        $this->assertContains('ana', $v);
        $this->assertContains('ана', $v);

        $v2 = MacedonianSearchVariants::variants('ана');
        $this->assertContains('ана', $v2);
        $this->assertContains('ana', $v2);
    }
}
