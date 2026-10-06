<?php

namespace Tests\Unit;

use App\Support\Usernames\UsernameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UsernameNormalizerTest extends TestCase
{
    /**
     * Spellings that must fold to one key (and so cannot coexist as two
     * accounts): case, script, Macedonian transliteration, diacritics read the
     * Macedonian way, leetspeak and separators.
     *
     * @return array<string, array{list<string>, string}>
     */
    public static function sameKey(): array
    {
        return [
            'case' => [['Marko', 'MARKO', 'marko'], 'marko'],
            'Cyrillic and Latin' => [['Марко', 'marko', 'МАРКО'], 'marko'],
            'digraphs' => [['Љубица', 'ljubica'], 'ljubica'],
            'ѓ ќ џ' => [['Ѓорѓи', 'gjorgji'], 'gjorgji'],
            'ш ж ч' => [['Шушка', 'shushka', 'šuška'], 'shushka'],
            'Albanian ç ë' => [['Çelë', 'chele', 'Челе'], 'chele'],
            'South Slavic č ć đ' => [['Čočević', 'Чочевиќ', 'chochevikj'], 'chochevikj'],
            'leetspeak' => [['m4rk0', 'MARK0', 'm@rko', 'marko'], 'marko'],
            'separators' => [['m.a.r.k.o', 'mar_ko', 'mar-ko', 'marko'], 'marko'],
            'full-width and mathematical letters' => [['Ａｄｍｉｎ', '𝐀𝐝𝐦𝐢𝐧', 'admin'], 'admin'],
            'Greek look-alikes' => [['Αdmin', 'αdmin'], 'admin'],
        ];
    }

    /**
     * @param  list<string>  $spellings
     */
    #[DataProvider('sameKey')]
    public function test_spellings_fold_to_one_key(array $spellings, string $key): void
    {
        foreach ($spellings as $spelling) {
            $this->assertSame($key, UsernameNormalizer::key($spelling), $spelling);
        }
    }

    public function test_the_skeleton_catches_look_alikes_that_transliteration_reads_differently(): void
    {
        // Cyrillic „рара“ reads "rara" but looks exactly like Latin „papa“.
        $this->assertSame('rara', UsernameNormalizer::key('рара'));
        $this->assertSame(UsernameNormalizer::skeleton('papa'), UsernameNormalizer::skeleton('рара'));

        // Upper-case Cyrillic СОСК looks like COCK.
        $this->assertSame('cock', UsernameNormalizer::skeleton('СОСК'));

        // l, 1 and I are the same stroke.
        $this->assertSame(UsernameNormalizer::skeleton('Ilija'), UsernameNormalizer::skeleton('llija'));
        $this->assertSame(UsernameNormalizer::skeleton('marko1'), UsernameNormalizer::skeleton('markol'));

        // Different names stay different.
        $this->assertNotSame(UsernameNormalizer::skeleton('ana'), UsernameNormalizer::skeleton('anna'));
    }

    public function test_collapse_and_tokens(): void
    {
        $this->assertSame('fuck', UsernameNormalizer::collapse('fuuuuck'));
        $this->assertContains('dr', UsernameNormalizer::tokens('dr.marko'));
        $this->assertContains('dr', UsernameNormalizer::tokens('Dr1'));
        $this->assertContains('д-р', UsernameNormalizer::tokens('д-р.петар'));
        $this->assertContains('sh1t', UsernameNormalizer::tokens('sh1t_x'));
    }
}
