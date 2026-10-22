<?php

namespace App\Support\Search;

/**
 * PHP twin of PostgreSQL's pg_trgm similarity measures, for the SQLite test
 * database (no pg_trgm) and for the small in-memory lists (specialties) that
 * are not worth a query. Mirrors pg_trgm's trigram extraction: lower-cased,
 * words are runs of letters/digits, each padded with two leading blanks and
 * one trailing blank.
 */
final class Trigram
{
    /**
     * @return array<string, true>
     */
    public static function set(string $text): array
    {
        $set = [];

        foreach (self::words($text) as [$word]) {
            $padded = '  '.mb_strtolower($word, 'UTF-8').' ';
            $length = mb_strlen($padded, 'UTF-8');

            for ($i = 0; $i + 3 <= $length; $i++) {
                $set[mb_substr($padded, $i, 3, 'UTF-8')] = true;
            }
        }

        return $set;
    }

    /** pg_trgm similarity(): shared trigrams over all distinct trigrams. */
    public static function similarity(string $a, string $b): float
    {
        return self::jaccard(self::set($a), self::set($b));
    }

    /**
     * pg_trgm strict_word_similarity(): the best similarity between the needle
     * and any run of whole words of the haystack. Also returns that run, as
     * written in the haystack — „domenika“ in „Доминика Славкоска“ gives
     * „Доминика“, the word a „Дали мислевте“ suggestion offers.
     *
     * Runs are capped at the needle's word count + 2: longer ones only add
     * trigrams the needle does not have.
     *
     * @return array{0: float, 1: string}
     */
    public static function bestExtent(string $needle, string $haystack): array
    {
        $needleSet = self::set($needle);
        $words = self::words($haystack);

        if ($needleSet === [] || $words === []) {
            return [0.0, ''];
        }

        $maxRun = count(self::words($needle)) + 2;
        $best = 0.0;
        $bestText = '';

        foreach (array_keys($words) as $start) {
            $run = [];

            for ($end = $start; $end < count($words) && $end - $start < $maxRun; $end++) {
                $run[] = $words[$end][0];
                $score = self::jaccard($needleSet, self::set(implode(' ', $run)));

                if ($score > $best) {
                    $best = $score;
                    $offset = $words[$start][1];
                    $bestText = substr($haystack, $offset, $words[$end][1] + strlen($words[$end][0]) - $offset);
                }
            }
        }

        return [$best, $bestText];
    }

    /**
     * Similarity of a one-word needle to the start of each haystack word, cut
     * to the needle's length + 1: what someone typing the stem of a longer
     * word means („kardiolog“ for „Кардиологија“). Multi-word needles fall
     * back to bestExtent(). Returns the whole matched word.
     *
     * @return array{0: float, 1: string}
     */
    public static function bestPrefix(string $needle, string $haystack): array
    {
        $needleWords = self::words($needle);

        if (count($needleWords) !== 1) {
            return self::bestExtent($needle, $haystack);
        }

        $needleSet = self::set($needle);
        $length = mb_strlen($needleWords[0][0], 'UTF-8') + 1;
        $best = 0.0;
        $bestText = '';

        foreach (self::words($haystack) as [$word]) {
            $score = self::jaccard($needleSet, self::set(mb_substr($word, 0, $length, 'UTF-8')));

            if ($score > $best) {
                $best = $score;
                $bestText = $word;
            }
        }

        return [$best, $bestText];
    }

    /**
     * @param  array<string, true>  $a
     * @param  array<string, true>  $b
     */
    private static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $shared = count(array_intersect_key($a, $b));

        return $shared / (count($a) + count($b) - $shared);
    }

    /**
     * Words with their byte offsets.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function words(string $text): array
    {
        if (preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        // Offsets are -1 only for groups that did not take part; the whole
        // match always does.
        return array_map(static fn (array $match): array => [$match[0], max(0, $match[1])], $matches[0]);
    }
}
