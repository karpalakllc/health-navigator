<?php

namespace App\Support\Import\Names;

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
 * legal form inside the name, a role word in the middle, a Latin letter with
 * no Cyrillic twin inside a Cyrillic word, text after a comma that is not a
 * title. Then `value` is the input exactly as given (no partial cleaning)
 * and `suggestion` the proposal for the review item, if any. A name in
 * Latin script is cleaned as usual and flagged `latin_script`, with the
 * Cyrillic transliteration as `suggestion` — none when a letter has no
 * certain Cyrillic counterpart.
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

    /** Words that are never part of a person's name (lower case). */
    private const NOT_A_NAME = ['и', 'за', 'со', 'на', 'во', 'од', 'по', 'до', 'при', 'кај'];

    /**
     * Latin → Macedonian Cyrillic for names (lower case; strtr takes the
     * longest match first). Albanian spelling as Macedonian writes Albanian
     * names („Xhaferi“ → „Џафери“, „Qazim“ → „Ќазим“, „Thaçi“ → „Тачи“):
     * xh → џ, gj → ѓ, zh → ж, sh → ш, ç → ч, q → ќ, dh → д, th → т, ll → л,
     * rr → р, nj → њ, ë → е, y → и (ј before a vowel). Serbo-Croatian:
     * č ć š ž đ dž lj nj, and „dj“ (đ typed without the accent) → ѓ
     * („Djordjevic“ → „Ѓорѓевиќ“). The ASCII Macedonian digraphs (kj, ch,
     * dzh, dz) as MacedonianSearchVariants reads them. A plain x (Albanian
     * ѕ, but кс in „Maxim“), w and other letters without one certain
     * counterpart are not mapped: the name then gets no proposal.
     *
     * @var array<string, string>
     */
    private const LATIN_TO_CYRILLIC = [
        'dzh' => 'џ', 'xh' => 'џ', 'dž' => 'џ', 'gj' => 'ѓ', 'dj' => 'ѓ', 'kj' => 'ќ', 'lj' => 'љ', 'nj' => 'њ',
        'zh' => 'ж', 'sh' => 'ш', 'ch' => 'ч', 'dh' => 'д', 'th' => 'т', 'll' => 'л', 'rr' => 'р', 'dz' => 'ѕ',
        'a' => 'а', 'b' => 'б', 'c' => 'ц', 'ç' => 'ч', 'č' => 'ч', 'ć' => 'ќ', 'd' => 'д', 'đ' => 'ѓ', 'e' => 'е',
        'ë' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'ј', 'k' => 'к', 'l' => 'л', 'm' => 'м',
        'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'ќ', 'r' => 'р', 's' => 'с', 'š' => 'ш', 't' => 'т', 'u' => 'у',
        'v' => 'в', 'y' => 'и', 'z' => 'з', 'ž' => 'ж',
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
        // „Ана Петрова - специјалист по педијатрија“: a spaced dash before a
        // role or a title separates like a comma (a double surname „Петрова -
        // Ристова“ is not followed by one).
        if (! str_contains($name, ',') && preg_match('/^(.*?\S)\s+-\s+(\S+)(.*)$/u', $name, $dash) === 1
            && (self::isRole($dash[2]) || self::isTitle($dash[2]))) {
            $name = $dash[1].', '.$dash[2].$dash[3];
        }

        if (str_contains($name, ',')) {
            [$left, $right] = array_map('trim', explode(',', $name, 2));
            $tail = DoctorTitle::clean($right);

            if ($tail !== null && $tail->uncertain === null && count(self::words($left)) >= 2) {
                $title = $tail->value !== '' ? $tail->value : null;
                $changes[] = $title !== null ? 'title_in_name' : 'role_in_name';
                $name = $left;
            } else {
                $uncertain = 'text_after_comma';
                $ignored = [];
                $suggestion = self::finish($left, $ignored);
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
            $flagged = array_keys($institution + $roles);
            $suggestion ??= self::nameRun(array_slice($words, 0, min($flagged)))
                ?? self::nameRun(array_slice($words, max($flagged) + 1));
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

        // Uncertain: the value stays exactly as it was — a partly cleaned
        // name („Ана Петрова-Специјалист По Педијатрија“) reads worse than
        // the original — and the proposal goes to the review item only.
        if ($uncertain !== null) {
            return new CleanedName($raw, [], $uncertain, $suggestion !== $raw ? $suggestion : null);
        }

        $name = self::finish($name, $changes);

        // Latin script: every rule above applied with confidence (casing, a
        // title moved out); only the script is a person's decision.
        if (Homoglyphs::isLatinOnly($name)) {
            $uncertain = 'latin_script';
            $suggestion = self::transliterate($name);
        }

        return new CleanedName($name, array_values(array_unique($changes)), $uncertain, $suggestion !== $name ? $suggestion : null, $title);
    }

    /**
     * Words that can be a whole name on their own (two or more, none a
     * function word, role, title or institution word), finished; else null.
     *
     * @param  list<string>  $words
     */
    private static function nameRun(array $words): ?string
    {
        $words = array_values(array_filter($words, fn (string $word): bool => trim($word, '-.,') !== ''));

        if (count($words) < 2) {
            return null;
        }

        foreach ($words as $word) {
            $bare = self::bare($word);

            if (in_array($bare, self::NOT_A_NAME, true) || in_array($bare, self::INSTITUTION_WORDS, true)
                || self::isRole($word) || self::isTitle($word)) {
                return null;
            }
        }

        $ignored = [];

        return self::finish(implode(' ', $words), $ignored);
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

        // A title word left inside a name keeps its form („д-р“, never „Д-Р“).
        $cased = implode(' ', array_map(fn (string $word): string => self::isTitle($word) ? $word : self::casing($word), self::words($name)));

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

    /**
     * The Cyrillic spelling of a Latin-script name, or null when a letter
     * has no certain Macedonian counterpart (a person writes it). See
     * LATIN_TO_CYRILLIC for the conventions.
     */
    private static function transliterate(string $name): ?string
    {
        $words = [];

        foreach (self::words($name) as $word) {
            // Bosnian/Serbian „-ić“ written without the accent.
            $word = (string) preg_replace(['/ic$/u', '/IC$/u'], ['ić', 'IĆ'], $word);
            $lower = mb_strtolower($word, 'UTF-8');
            // Albanian and Turkish „y“: ј before a vowel („Yusuf“), else the
            // vowel и („Ylber“, „Gjyla“).
            $lower = (string) preg_replace('/y(?=[aeiouë])/u', 'ј', $lower);
            $words[] = strtr($lower, self::LATIN_TO_CYRILLIC);
        }

        $cyrillic = implode(' ', $words);

        if (preg_match('/[\p{Latin}]/u', $cyrillic) === 1) {
            return null;
        }

        $ignored = [];

        return self::finish($cyrillic, $ignored);
    }

    /**
     * Whether a (cleaned) name still holds a role, title or institution word
     * — a sign an earlier cleanup kept a partly cleaned uncertain name.
     */
    public static function holdsNonNameWord(string $name): bool
    {
        foreach (self::words((string) preg_replace('/-/u', ' ', $name)) as $word) {
            if (self::isRole($word) || self::isTitle($word) || in_array(self::bare($word), self::INSTITUTION_WORDS, true)) {
                return true;
            }
        }

        return false;
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
