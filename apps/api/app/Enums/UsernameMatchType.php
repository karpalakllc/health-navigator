<?php

namespace App\Enums;

/**
 * How a username term is matched against a username's folded forms
 * (App\Support\Usernames\UsernameNormalizer).
 */
enum UsernameMatchType: string
{
    /**
     * The whole username, or one of its words („dr.marko“, „marko-dr“, „dr1“),
     * equals the term. The default for short terms, which as substrings would
     * refuse ordinary names („dr“ in „Dragan“, „ass“ in „Vasil“).
     */
    case Exact = 'exact';

    /**
     * The term appears anywhere in the username. Only for terms of at least
     * four letters that do not occur inside real names.
     */
    case Contains = 'contains';

    /**
     * As `exact`, and also at the start of the username or of a word when a
     * consonant follows („drmarko“, „profivanov“ → refused), for titles
     * people glue to a name. A vowel after the term reads as a name of its
     * own (Dragan, Drita, Mjeku), so it is let through.
     */
    case Prefix = 'prefix';

    /**
     * An exception: a word that contains a listed term but is fine
     * („Scunthorpe“). Its occurrences are masked before `contains` terms are
     * checked, so it excuses only itself — „scunthorpe_fuck“ is still refused.
     */
    case Allowed = 'allowed';

    /** Below this many letters a term may only be matched exactly (unless staff choose otherwise). */
    public const CONTAINS_MIN_LENGTH = 4;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact word',
            self::Contains => 'Contains',
            self::Prefix => 'Word start (before a consonant)',
            self::Allowed => 'Allowed (exception)',
        };
    }
}
