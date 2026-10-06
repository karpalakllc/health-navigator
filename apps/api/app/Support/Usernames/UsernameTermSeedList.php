<?php

namespace App\Support\Usernames;

use App\Enums\UsernameMatchType;

/**
 * The shipped blocked and reserved username terms as table rows, from
 * database/seeders/data/username_terms.php (curated for this project) and
 * username_terms_ldnoobw_en.php (LDNOOBW, CC BY 4.0).
 *
 * Inserted by the 2026_10_14_100002 migration with insertOrIgnore, so terms
 * staff edited, deactivated or deleted are not brought back by a later run
 * — only terms that were never there are added.
 */
final class UsernameTermSeedList
{
    /**
     * LDNOOBW terms left out: ordinary words or names in this context
     * („lolita“ is a first name here, „domination“ and „escort“ are mostly
     * innocent), phrases too vague to mean anything once their spaces are
     * gone, and entries the username alphabet cannot contain.
     */
    private const LDNOOBW_SKIPPED = [
        'domination', 'lolita', 'shota', 'santorum', 'mong', 'xx', '🖕', 's&m', 'girl on', 'tainted love', 'jelly donut',
        'tongue in a', 'taste my', 'tight white', 'hot chick', 'huge fat', 'bastinado', 'big black', 'fingering',
        'hard core', 'hardcore', 'how to kill', 'how to murder', 'dog style', 'style doggy', 'tied up', 'grope',
    ];

    /**
     * LDNOOBW terms of five letters or more that occur inside names or
     * ordinary words, so they are matched as whole words only: Cummings,
     * Semenov, Montenegro, trimming, draping, Vecchi, Shota Rustaveli.
     */
    private const LDNOOBW_EXACT = [
        'cumming', 'semen', 'escort', 'twink', 'boner', 'skeet', 'snatch', 'negro', 'raping', 'ecchi',
        'rimming', 'sucks', 'cocks', 'coons', 'nudity', 'pubes', 'tushy', 'figging', 'pegging', 'dommes',
        'nutten', 'erotic', 'panty', 'humping', 'busty', 'bimbos',
    ];

    /**
     * @return list<array{term: string, term_normalized: string, term_skeleton: string, kind: string, language: string, match_type: string, category: string, note: string|null}>
     */
    public static function rows(): array
    {
        $rows = [];

        /** @var list<array{kind: string, category: string, language: string, contains?: list<string>, exact?: list<string>, allowed?: list<string>}> $groups */
        $groups = require database_path('seeders/data/username_terms.php');

        foreach ($groups as $group) {
            foreach (['contains', 'exact', 'allowed'] as $type) {
                foreach ($group[$type] ?? [] as $term) {
                    $rows[] = self::row($term, $group['kind'], $group['language'], self::safeType($term, $type), $group['category'], null);
                }
            }
        }

        /** @var list<string> $ldnoobw */
        $ldnoobw = require database_path('seeders/data/username_terms_ldnoobw_en.php');

        foreach ($ldnoobw as $term) {
            if (in_array($term, self::LDNOOBW_SKIPPED, true) || UsernameNormalizer::key($term) === '') {
                continue;
            }

            $type = mb_strlen(UsernameNormalizer::key($term)) >= 5 && ! in_array($term, self::LDNOOBW_EXACT, true)
                ? 'contains'
                : 'exact';

            $rows[] = self::row($term, 'blocked', 'en', $type, 'profanity', 'LDNOOBW (CC BY 4.0)');
        }

        // First occurrence wins, as insertOrIgnore would decide it.
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row['kind'].'|'.$row['match_type'].'|'.$row['term']] ??= $row;
        }

        return array_values($unique);
    }

    /**
     * A `contains` term shorter than four letters once folded would refuse
     * ordinary names, whatever the list says: it is matched as a word instead.
     */
    private static function safeType(string $term, string $type): string
    {
        if ($type === UsernameMatchType::Contains->value && mb_strlen(UsernameNormalizer::key($term)) < UsernameMatchType::CONTAINS_MIN_LENGTH) {
            return UsernameMatchType::Exact->value;
        }

        return $type;
    }

    /**
     * @return array{term: string, term_normalized: string, term_skeleton: string, kind: string, language: string, match_type: string, category: string, note: string|null}
     */
    private static function row(string $term, string $kind, string $language, string $type, string $category, ?string $note): array
    {
        $term = mb_strtolower(UsernameNormalizer::prepare($term));

        return [
            'term' => $term,
            'term_normalized' => UsernameNormalizer::key($term),
            'term_skeleton' => UsernameNormalizer::skeleton(UsernameNormalizer::key($term)),
            'kind' => $kind,
            'language' => $language,
            'match_type' => $type,
            'category' => $category,
            'note' => $note,
        ];
    }
}
