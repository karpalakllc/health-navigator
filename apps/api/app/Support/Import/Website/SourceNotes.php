<?php

namespace App\Support\Import\Website;

/**
 * Warnings the researchers wrote into an institution's `notes` in
 * institutions.json about the website itself: a compromised site (injected
 * spam or gambling pages) or a stale one (not updated for years). A staff
 * list from such a site is not evidence enough to verify a doctor
 * (docs/verification.md); the verification engine asks staff instead, once
 * per site.
 *
 * Keyword matching on free text: it errs towards flagging (a false flag
 * costs one „Trust this website“ click, a missed one a wrong badge).
 */
final class SourceNotes
{
    public const COMPROMISED = 'compromised';

    public const STALE = 'stale';

    /** @var array<string, string> flag => pattern */
    private const PATTERNS = [
        self::COMPROMISED => '/compromis|injected|hacked|\bspam\b|casino|gambling|коцк|казино|хакиран|компромит/iu',
        self::STALE => '/\bstale\b|outdated|\bdated\b|unmaintained|not (?:been )?updated|(?:last|latest) (?:news|update|post)s? (?:from |in )?(?:19|20)\d\d|застар|неажур|не се ажурира|напуштен/iu',
    ];

    /**
     * @return list<string>
     */
    public static function flags(?string $notes): array
    {
        if ($notes === null || trim($notes) === '') {
            return [];
        }

        $flags = [];

        foreach (self::PATTERNS as $flag => $pattern) {
            if (preg_match($pattern, $notes) === 1) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }

    /**
     * The sentence that raised the first flag, for the staff review item.
     */
    public static function excerpt(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        foreach (preg_split('/(?<=[.!?;])\s+/u', $notes) ?: [] as $sentence) {
            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, $sentence) === 1) {
                    return mb_substr(trim($sentence), 0, 240);
                }
            }
        }

        return null;
    }
}
