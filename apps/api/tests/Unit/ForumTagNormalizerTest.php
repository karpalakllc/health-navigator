<?php

namespace Tests\Unit;

use App\Support\Forum\ForumTagNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ForumTagNormalizerTest extends TestCase
{
    public function test_name_lowercases_trims_and_drops_punctuation(): void
    {
        $this->assertSame('проширени вени', ForumTagNormalizer::name('  #Проширени   ВЕНИ!! '));
        $this->assertSame('covid-19', ForumTagNormalizer::name('COVID - 19'));
    }

    public function test_name_rejects_too_short_too_long_and_bare_numbers(): void
    {
        $this->assertNull(ForumTagNormalizer::name('а'));
        $this->assertNull(ForumTagNormalizer::name('!!!'));
        $this->assertNull(ForumTagNormalizer::name('2026'));
        $this->assertNull(ForumTagNormalizer::name(str_repeat('а', 41)));
    }

    public function test_latin_is_the_diacritic_free_spelling_people_type(): void
    {
        $this->assertSame('operacija za prosireni veni', ForumTagNormalizer::latin('операција за проширени вени'));
        $this->assertSame('dzvonce lj nj dz', ForumTagNormalizer::latin('ѕвонце љ њ џ'));
        $this->assertSame('kerka gavol', ForumTagNormalizer::latin('ќерка ѓавол'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sameKeyProvider(): array
    {
        return [
            'shaved latin' => ['проширени вени', 'prosireni veni'],
            'digraph latin' => ['проширени вени', 'proshireni veni'],
            'diacritics' => ['проширени вени', 'prošireni veni'],
            'kj and gj' => ['ќесичка ѓубре', 'kjesicka gjubre'],
            'hyphen vs space' => ['бајпас-операција', 'bajpas operacija'],
        ];
    }

    #[DataProvider('sameKeyProvider')]
    public function test_spellings_of_the_same_words_share_a_match_key(string $a, string $b): void
    {
        $this->assertSame(
            ForumTagNormalizer::matchKey((string) ForumTagNormalizer::name($a)),
            ForumTagNormalizer::matchKey((string) ForumTagNormalizer::name($b)),
        );
    }

    public function test_slug_is_ascii(): void
    {
        $this->assertSame('prosireni-veni', ForumTagNormalizer::slug('проширени вени'));
    }

    public function test_list_dedupes_by_match_key_and_caps_at_five(): void
    {
        $this->assertSame(
            ['проширени вени', 'операција', 'кардиологија', 'скопје', 'цена'],
            ForumTagNormalizer::list([
                'Проширени вени', 'prosireni veni', 'операција', '1', 'кардиологија', 'скопје', 'цена', 'болница',
            ]),
        );
    }
}
