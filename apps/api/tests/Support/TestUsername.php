<?php

namespace Tests\Support;

/**
 * A fresh, valid username for a registration request in a test: „novakb“,
 * „novakc“, … Letters only, one word and deterministic, so it cannot fold into
 * a listed word by chance the way a random hex string can (and no two-letter
 * suffix becomes a word of its own, like „novak_dr“).
 */
final class TestUsername
{
    private static int $counter = 0;

    public static function next(): string
    {
        $n = ++self::$counter;
        $letters = '';

        while ($n > 0) {
            $letters = chr(97 + ($n % 26)).$letters;
            $n = intdiv($n, 26);
        }

        return 'novak'.$letters;
    }
}
