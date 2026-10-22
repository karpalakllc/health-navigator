<?php

namespace App\Support\Import\Pharmacies;

use App\Support\Import\NameKey;
use App\Support\Import\Names\CleanedName;
use App\Support\Import\Names\FacilityName;

/**
 * Display names for the pharmacy branches of ФЗОМ's register.
 *
 * ФЗОМ lists a pharmacy contract unit with the legal entity
 * (`ZdravstvenaUstanova`, „ПЗУ АПТЕКА ЗЕГИН ФАРМ“) and the branch
 * (`RabotnaEdinica`, „ПРИВАТНА ЗДРАВСТВЕНА УСТАНОВА-АПТЕКА ЗЕГИН ФАРМ
 * СКОПЈЕ-ПОДРУЖНИЦА АПТЕКА ЗЕГИН ФАРМ УНИВЕРЗАЛНА“). The profile shows the
 * branch as people know it: „Аптека Зегин Фарм Универзална“.
 *
 * 1. The branch name, or the legal name when the branch is empty.
 * 2. After „ПОДРУЖНИЦА“ only the branch part; when that part does not carry
 *    the chain's name („… ПОДРУЖНИЦА СТРУМИЦА 4“) the chain's name goes in
 *    front („Моја Аптека Струмица 4“).
 * 3. The legal form („ПЗУ“, „П.З.У.“, „Приватна здравствена установа“) is
 *    dropped: every branch is a private or public health institution, the
 *    ownership field says which, and people do not say „ПЗУ“.
 * 4. „Аптека“ in front when no word of the name is one already
 *    („НОВАЛЕК“ → „Аптека Новалек“).
 * 5. FacilityName::clean for the rest (casing, quotes, homoglyphs, spacing,
 *    the town at the end), except that a name left as only a generic word
 *    („Аптека“, „Аптекарска станица“) keeps the town („Аптека Неготино“).
 */
final class PharmacyName
{
    private const LEGAL_FORM = '/^\s*(?:ПРИВАТ\p{L}*\s+ЗДРА\p{L}*\s+УСТАНОВА|ЈАВНА\s+ЗДРА\p{L}*\s+УСТАНОВА|П\.\s*З\.\s*У\.?|Ј\.\s*З\.\s*У\.?|ПЗУ|ЈЗУ)(?![\p{L}])\s*[-–—,:]*\s*/iu';

    /**
     * „… ПОДРУЖНИЦА …“ (also „ПОДРУЖ.“, „ПОРДУЖНИЦА“), „… ОРГАНИЗАЦИОНА
     * ЕДИНИЦА …“ („ОРГ.ЕДИНИЦА“, „ОЕ“), „… БИТОЛА-АПТЕКА …“, „…, АПТЕКАРСКА
     * СТАНИЦА …“: the branch follows.
     */
    private const BRANCH = '/(?:[\s\-–—,:]*(?:(?:ПОДРУЖ\p{L}*|ПОРДУЖНИЦА|ОРГ(?:АНИЗАЦИОНА)?\.?\s*ЕДИНИЦА|ОЕ)(?![\p{L}])\.?|ПОДРЖ\.|ПОДР\.)|(?<=[\p{L}\d])\s*[-–—,]\s*(?=АПТЕК))\s*[-–—,:]*\s*/iu';

    public static function clean(string $branch, string $legalName, ?string $town): CleanedName
    {
        $legal = self::withoutLegalForm(self::spaced($legalName));
        $name = self::spaced($branch) !== '' ? self::spaced($branch) : self::spaced($legalName);
        $parts = preg_split(self::BRANCH, $name) ?: [$name];

        if (count($parts) > 1) {
            $tail = self::withoutLegalForm(trim((string) end($parts), ' -–—,:'));
            $chain = self::chain($legal);

            // „… ПОДРУЖНИЦА АПТЕКА РЕСЕН“: nothing but „Аптека“ (and the town).
            $bare = trim((string) preg_replace('/^АПТЕКА(?![\p{L}])/iu', '', $tail));

            if ($tail === '' || $bare === '' || ($town !== null && NameKey::for($bare) === NameKey::for((string) preg_split('/\s+[-–—]\s+/u', $town)[0]))) {
                $name = $chain !== '' ? $chain : $legal;
            } elseif ($chain !== '' && ! self::contains($tail, $chain)) {
                $name = $chain.' '.$tail;
            } else {
                $name = $tail;
            }
        } else {
            $name = self::withoutLegalForm($name);
        }

        // Stray dots ФЗОМ leaves before words („ПОДРУЖНИЦА .АПТЕКА …“).
        $name = trim((string) preg_replace('/(?<=^|\s)[.,]+(?=\p{L})/u', '', $name), ' .,-–—:');

        // A name that is only quotes or a legal form: the legal name.
        if (preg_match('/\p{L}/u', $name) !== 1) {
            $name = $legal;
        }

        if (preg_match('/(?<![\p{L}])АПТЕК/iu', $name) !== 1) {
            $name = 'АПТЕКА '.$name;
        }

        // „АПТЕКА МОЈА АПТЕКА ОХРИД 4“: the chain's own name says it.
        if (preg_match('/^АПТЕКА\s+(?=.*(?<![\p{L}])АПТЕК)/iu', $name) === 1) {
            $name = (string) preg_replace('/^АПТЕКА\s+/iu', '', $name);
        }

        $cleaned = FacilityName::clean($name, $town);
        $value = (string) preg_replace('/(?<![\p{L}])(Аптекарска) Станица(?![\p{L}])/u', '$1 станица', $cleaned->value);

        // „ПЗУ АПТЕКА НЕГОТИНО“: dropping the town left only a generic word.
        // Then the town is the name: „Аптека Неготино“.
        $townName = $town !== null ? trim((string) (preg_split('/\s+[-–—]\s+/u', $town)[0] ?? '')) : '';

        if ($townName !== '' && in_array(mb_strtolower($value, 'UTF-8'), ['аптека', 'аптекарска станица'], true)) {
            $value .= ' '.$townName;
        }

        return new CleanedName(mb_substr($value, 0, 255), $cleaned->changes, $cleaned->uncertain);
    }

    /**
     * Ownership as the register states it in the legal name.
     */
    public static function ownership(string $legalName): string
    {
        $upper = mb_strtoupper($legalName, 'UTF-8');

        return match (true) {
            preg_match('/^(?:ЈЗУ|Ј\.\s*З\.\s*У|ЈАВНА)/u', $upper) === 1 => 'public',
            default => 'private',
        };
    }

    /**
     * The chain's name without „Аптека“ and the legal form („ПЗУ АПТЕКА
     * МОЈА АПТЕКА“ → „МОЈА АПТЕКА“, „ПЗУ АПТЕКА ЗЕГИН ФАРМ“ → „ЗЕГИН ФАРМ“).
     */
    private static function chain(string $legal): string
    {
        $chain = trim((string) preg_replace('/^АПТЕКА\s*[-–—]?\s*/iu', '', $legal));

        return $chain !== '' ? $chain : $legal;
    }

    /**
     * Whether the branch part already names the chain: one of the chain's
     * own words („ЕУРОФАРМ“ of „ЕУРОФАРМ БИТОЛА“) is in it. „Фарм“, „Лек“
     * and „Аптека“ name every chain.
     */
    private static function contains(string $haystack, string $needle): bool
    {
        $words = array_filter(
            explode(' ', NameKey::for($needle)),
            fn (string $word): bool => mb_strlen($word) > 1 && ! in_array($word, ['АПТЕКА', 'ФАРМ', 'ФАРМА', 'ЛЕК', 'ДООЕЛ', 'ДОО'], true),
        );
        $have = explode(' ', NameKey::for($haystack));

        return $words === [] || array_intersect($words, $have) !== [];
    }

    private static function withoutLegalForm(string $name): string
    {
        $before = null;

        while ($before !== $name) {
            $before = $name;
            $name = (string) preg_replace(self::LEGAL_FORM, '', $name);
            // „ПАНАКС-АПТЕКА П.З.У.“: a legal form at the end too.
            $name = (string) preg_replace('/[\s,\-–—]+(?:П\.\s*З\.\s*У\.?|ПЗУ)\s*$/iu', '', $name);
        }

        return trim($name);
    }

    private static function spaced(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
