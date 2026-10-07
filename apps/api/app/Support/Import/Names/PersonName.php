<?php

namespace App\Support\Import\Names;

use App\Support\MacedonianSearchVariants;

/**
 * Cleans a doctor's name as a source wrote it („Д-Р АНА ПЕТРОВА - РИСТОВА“,
 * „Ана Петрова, стоматолог“, „Бaјрaми“ with a Latin „a“) into the form the
 * directory shows: given name first, „Ана Петрова-Ристова“, and any title in
 * the title field.
 *
 * High-confidence fixes only (each listed in `changes`):
 * - `spacing`, `quotes`: whitespace, stray quotes and dashes;
 * - `title_in_name`: leading or trailing academic titles move to `title`;
 * - `role_in_name`: a trailing role or profession („стоматолог“, „доктор“,
 *   „специјалист …“) is dropped;
 * - `homoglyph`: Latin look-alike letters inside Cyrillic words;
 * - `hyphen`: „Петрова - Ристова“ → „Петрова-Ристова“ (a double surname);
 * - `initial`: „К.Петрова“ → „К. Петрова“, „Ана К Петрова“ → „Ана К. Петрова“,
 *   a stray dot after the given name or before a word;
 * - `casing`: a word in all capitals or all lower case is capitalised
 *   (each part of a hyphenated surname); mixed case is left alone.
 *
 * Everything else is `uncertain` (a person decides): an institution or
 * legal form inside the name, a role word in the middle, a name in Latin
 * script (`suggestion`: the Cyrillic transliteration), a Latin letter with
 * no Cyrillic twin inside a Cyrillic word, text after a comma that is not a
 * title.
 *
 * The matching keys (App\Support\Import\NameKey) of a cleaned name equal the
 * original's, except where the original glued words together („К.Петрова“)
 * or carried a title NameKey keeps („Др Ана Петрова“).
 */
final class PersonName
{
    /** Professions and roles that are not part of a name (lower case). */
    private const ROLE_WORDS = [
        'стоматолог', 'стоматологот', 'доктор', 'докторка', 'лекар', 'лекарка', 'специјалист', 'специјалистка',
        'специјализант', 'специјализантка', 'дипл', 'дипломиран', 'дипломирана', 'стом', 'мед', 'dentist', 'doctor',
        'дентист', 'ортодонт', 'педијатар', 'гинеколог', 'хирург', 'интернист', 'кардиолог', 'невролог',
        'офталмолог', 'дерматолог', 'психијатар', 'радиолог', 'уролог', 'анестезиолог', 'оториноларинголог',
    ];

    /** Words of an institution or a legal form (lower case). */
    private const INSTITUTION_WORDS = [
        'пзу', 'јзу', 'зу', 'доо', 'дооел', 'ад', 'ординација', 'поликлиника', 'клиника', 'болница', 'центар',
        'здравствен', 'здравствена', 'дом', 'стоматолошка', 'амбуланта', 'лабораторија', 'аптека', 'институт',
        'медика', 'дент', 'дентал', 'clinic', 'dental',
    ];

    /** @var array<string, string> letters MacedonianSearchVariants does not know */
    private const LATIN_LETTERS = [
        'ć' => 'ќ', 'Ć' => 'Ќ', 'č' => 'ч', 'Č' => 'Ч', 'š' => 'ш', 'Š' => 'Ш', 'ž' => 'ж', 'Ž' => 'Ж',
        'đ' => 'ѓ', 'Đ' => 'Ѓ', 'ç' => 'ч', 'Ç' => 'Ч', 'ë' => 'е', 'Ë' => 'Е', 'dj' => 'џ', 'Dj' => 'Џ', 'y' => 'ј', 'Y' => 'Ј',
    ];

    public static function clean(string $raw): CleanedName
    {
        $changes = [];
        $name = self::whitespace($raw);

        if ($name !== $raw) {
            $changes[] = 'spacing';
        }

        // Quotes and look-alike dashes.
        $unquoted = trim((string) preg_replace('/[„“”"«»‚‘’`]+/u', ' ', $name));
        $unquoted = trim((string) preg_replace('/\s+/u', ' ', $unquoted));

        if ($unquoted !== $name) {
            $changes[] = 'quotes';
            $name = $unquoted;
        }

        $name = (string) preg_replace('/[‐‑–—]/u', '-', $name);

        $title = null;
        $uncertain = null;
        $suggestion = null;

        // „Ана Петрова, специјалист по педијатрија“: the part after a comma
        // is a title or role, or something a person has to read.
        if (str_contains($name, ',')) {
            [$left, $right] = array_map('trim', explode(',', $name, 2));
            $tail = DoctorTitle::clean($right);

            if ($tail !== null && $tail->uncertain === null && count(self::words($left)) >= 2) {
                $title = $tail->value !== '' ? $tail->value : null;
                $changes[] = $title !== null ? 'title_in_name' : 'role_in_name';
                $name = $left;
            } else {
                $uncertain = 'text_after_comma';
                $suggestion = $left;
            }
        }

        $words = self::words($name);

        // Leading titles: „Д-р“, „Проф. д-р“, „dr.“ (a bare „Др“ too, when
        // a full name follows).
        $lead = [];

        while (count($words) > 2 && self::isTitle($words[0])) {
            $lead[] = array_shift($words);
        }

        // Trailing titles and roles: „Ана Петрова д-р“, „… стоматолог“.
        $trail = [];
        $roleDropped = false;

        while (count($words) > 2 && (self::isTitle(end($words)) || self::isRole(end($words)))) {
            $word = array_pop($words);

            if (self::isRole($word)) {
                $roleDropped = true;
            } else {
                array_unshift($trail, $word);
            }
        }

        if ($lead !== [] || $trail !== []) {
            $found = DoctorTitle::clean(implode(' ', [...$lead, ...$trail]));
            $title = $found?->uncertain === null ? ($found?->value ?: $title) : $title;
            $changes[] = 'title_in_name';
        }

        if ($roleDropped) {
            $changes[] = 'role_in_name';
        }

        // An institution or a role inside the name: only a person can say
        // which words are the name.
        $institution = array_filter($words, fn (string $word): bool => in_array(self::bare($word), self::INSTITUTION_WORDS, true));
        $roles = array_filter($words, fn (string $word): bool => self::isRole($word) || self::isTitle($word));

        if ($institution !== [] || $roles !== []) {
            $uncertain ??= $institution !== [] ? 'institution_in_name' : 'role_in_name';
            $last = max(array_keys($institution + $roles));
            $rest = array_slice($words, $last + 1);
            $ignored = [];
            $suggestion ??= count($rest) >= 2 ? self::finish(implode(' ', $rest), $ignored) : null;
        }

        $name = implode(' ', $words);

        $glyphs = Homoglyphs::repair($name);

        if ($glyphs['changed']) {
            $changes[] = 'homoglyph';
            $name = $glyphs['text'];
        }

        if ($glyphs['unresolved']) {
            $uncertain ??= 'mixed_script';
        }

        $name = self::finish($name, $changes);

        if ($uncertain === null && Homoglyphs::isLatinOnly($name)) {
            $uncertain = 'latin_script';
            $ignored = [];
            $suggestion = self::finish(self::transliterate($name), $ignored);
        }

        return new CleanedName($name, array_values(array_unique($changes)), $uncertain, $suggestion !== $name ? $suggestion : null, $title);
    }

    /**
     * Hyphens, initials and casing (the steps that never need a person).
     *
     * @param  list<string>  $changes
     */
    private static function finish(string $name, array &$changes): string
    {
        $hyphen = (string) preg_replace('/(?<=\p{L})\s*-\s*(?=\p{L})/u', '-', $name);

        if ($hyphen !== $name) {
            $changes[] = 'hyphen';
            $name = $hyphen;
        }

        $initials = self::initials($name);

        if ($initials !== $name) {
            $changes[] = 'initial';
            $name = $initials;
        }

        $cased = implode(' ', array_map(self::casing(...), self::words($name)));

        if ($cased !== $name) {
            $changes[] = 'casing';
            $name = $cased;
        }

        return $name;
    }

    private static function initials(string $name): string
    {
        // „К.Петрова“ → „К. Петрова“; „Максим.Петрова“ → „Максим. Петрова“.
        $name = (string) preg_replace('/(?<=\p{L})\.(?=\p{L})/u', '. ', $name);
        // „.Таири“ → „Таири“ (a dot before a word).
        $name = (string) preg_replace('/(?<=^|\s)\.+(?=\p{L})/u', '', $name);
        $words = self::words($name);
        $count = count($words);

        foreach ($words as $index => $word) {
            // A single letter between the given name and the surname is an
            // initial: „Ана К Петрова“ → „Ана К. Петрова“.
            if ($index > 0 && $index < $count - 1 && preg_match('/^\p{L}$/u', $word) === 1) {
                $words[$index] = $word.'.';
            }

            // „Ана. Петрова“: a dot after the full given name is a typo.
            if ($index === 0 && $count >= 2 && preg_match('/^\p{L}{3,}\.$/u', $word) === 1) {
                $words[$index] = rtrim($word, '.');
            }
        }

        return implode(' ', array_filter($words, fn (string $word): bool => $word !== '' && $word !== '.'));
    }

    private static function casing(string $word): string
    {
        return (string) preg_replace_callback('/\p{L}+/u', function (array $match): string {
            $part = $match[0];
            $lower = mb_strtolower($part, 'UTF-8');
            $upper = mb_strtoupper($part, 'UTF-8');

            if (mb_strlen($part) === 1) {
                return $upper;
            }

            // Mixed case is deliberate („МекДоналд“); only all-caps or
            // all-lower words are recased.
            if ($part !== $lower && $part !== $upper) {
                return $part;
            }

            return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($lower, 1, null, 'UTF-8');
        }, $word);
    }

    private static function transliterate(string $name): string
    {
        return implode(' ', array_map(
            fn (string $word): string => (string) preg_replace_callback(
                '/[A-Za-zČčŠšŽžĆćĐđÇçËë]+/u',
                fn (array $match): string => MacedonianSearchVariants::latinToCyrillic(strtr(
                    // Bosnian/Serbian „-ić“ written without the accent; „dj“ and
                    // Turkish „y“ as Macedonian spells them.
                    (string) preg_replace(['/ic$/u', '/IC$/u'], ['ić', 'IĆ'], $match[0]),
                    self::LATIN_LETTERS,
                )),
                $word,
            ),
            self::words($name),
        ));
    }

    private static function isTitle(string $word): bool
    {
        return DoctorTitle::isTitleWord($word);
    }

    private static function isRole(string $word): bool
    {
        return in_array(self::bare($word), self::ROLE_WORDS, true);
    }

    private static function bare(string $word): string
    {
        return mb_strtolower(trim($word, " \t.,;:()"), 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private static function words(string $name): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($name)) ?: [], fn (string $word): bool => $word !== ''));
    }

    private static function whitespace(string $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{2007}\x{202F}]+/u', ' ', $value));
    }
}
