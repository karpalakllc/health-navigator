<?php

namespace App\Support\Import\Names;

/**
 * Latin letters typed into Cyrillic words ("Бaјрaми" with a Latin "a"):
 * they look right but break search, sorting and copy-paste. Only letters
 * whose Latin and Cyrillic shapes are the same are folded, and only inside
 * a word that is otherwise Cyrillic; a wholly Latin word is a spelling, not
 * a typo.
 */
final class Homoglyphs
{
    /** @var array<string, string> */
    private const LATIN_TO_CYRILLIC = [
        'A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'J' => 'Ј', 'K' => 'К', 'M' => 'М',
        'O' => 'О', 'P' => 'Р', 'S' => 'Ѕ', 'T' => 'Т', 'X' => 'Х', 'Y' => 'У',
        'a' => 'а', 'c' => 'с', 'e' => 'е', 'j' => 'ј', 'o' => 'о', 'p' => 'р', 's' => 'ѕ', 'x' => 'х', 'y' => 'у',
    ];

    /**
     * Folds look-alike Latin letters in every mixed word. `unresolved` is
     * true when a mixed word keeps a Latin letter without a Cyrillic twin
     * ("Ивaнovski"): that needs a person.
     *
     * @return array{text: string, changed: bool, unresolved: bool}
     */
    public static function repair(string $text): array
    {
        $changed = false;
        $unresolved = false;

        $text = (string) preg_replace_callback('/[\p{L}]+/u', function (array $match) use (&$changed, &$unresolved): string {
            $word = $match[0];

            if (preg_match('/\p{Cyrillic}/u', $word) !== 1 || preg_match('/[A-Za-z]/', $word) !== 1) {
                return $word;
            }

            $folded = strtr($word, self::LATIN_TO_CYRILLIC);

            if ($folded !== $word) {
                $changed = true;
            }

            if (preg_match('/[A-Za-z]/', $folded) === 1) {
                $unresolved = true;
            }

            return $folded;
        }, $text);

        return ['text' => $text, 'changed' => $changed, 'unresolved' => $unresolved];
    }

    public static function isLatinOnly(string $text): bool
    {
        return preg_match('/[A-Za-z]/', $text) === 1 && preg_match('/\p{Cyrillic}/u', $text) !== 1;
    }
}
