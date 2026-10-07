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
    /**
     * Only letters whose Latin and Cyrillic shapes are the same AND whose
     * Cyrillic twin is the letter the typist meant. Not folded (a person
     * decides, `unresolved`): S/s (it looks like Ѕ/ѕ, but in a Macedonian
     * word it is almost always a mistyped С/с — folding it to ѕ reads right
     * and still breaks search), J/j (Ј is a Macedonian letter of its own, so a
     * person confirms it), Y (not the shape of У).
     *
     * @var array<string, string>
     */
    private const LATIN_TO_CYRILLIC = [
        'A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'K' => 'К', 'M' => 'М',
        'O' => 'О', 'P' => 'Р', 'T' => 'Т', 'X' => 'Х',
        'a' => 'а', 'c' => 'с', 'e' => 'е', 'o' => 'о', 'p' => 'р', 'x' => 'х', 'y' => 'у',
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
