<?php

namespace App\Support\Import\Names;

use App\Support\Import\NameKey;
use App\Support\Import\TextCase;

/**
 * Display names for facilities. The ФЗОМ register writes names in capitals
 * with abbreviations, glued legal forms and the town at the end („ПЗУ-ОРД.ПО
 * ОПШТА МЕДИЦИНА "Д-Р АНА" СКОПЈЕ“); websites mostly write them well.
 *
 * High-confidence fixes (listed in `changes`):
 * - `casing`: capitals → TextCase::institution; „Д-Р“ → „д-р“, „Др“ before a
 *   name → „д-р“; function words („по“, „за“, „и“…) and generic words in a
 *   descriptive run („Здравствен Дом“ → „Здравствен дом“, „Ординација По
 *   Општа Медицина“ → „Ординација по општа медицина“) in lower case;
 * - `legal_form`: „Пзу-“, „П.З.У.“, „Приватна здравствена установа -“ → „ПЗУ “
 *   (ЈЗУ likewise);
 * - `abbreviation`: „Орд.“ → „Ординација“, „Спец. Орд.“ → „Специјалистичка
 *   ординација“, „Поликл.“, „Опш.“, „Кл.“, „Универзи.“/„Унив.“ written out;
 *   any other „Аа.Бб“ gets its space;
 * - `quotes`: ’’…’’, "…", “…“, «…» → „…“;
 * - `town`: the facility's own town (or village, „С. Х“) at the end of a
 *   private institution's name, when the name says enough without it —
 *   the town is shown next to the name anyway. Public institutions (ЈЗУ,
 *   health centres, hospitals) keep it: there the town is the name;
 * - `homoglyph`, `spacing`.
 */
final class FacilityName
{
    /** Written out without doubt (lower case key, any dot spacing). */
    private const ABBREVIATIONS = [
        'спец\.\s*орд\.?' => 'Специјалистичка ординација',
        'спец\.\s*(?=ординација)' => 'Специјалистичка ',
        'орд\.' => 'Ординација',
        'поликл\.' => 'Поликлиника',
        'опш\.' => 'Општа',
        'универзи\.\s*кл\.' => 'Универзитетска клиника',
        'унив\.\s*кл\.' => 'Универзитетска клиника',
        'кл\.(?=\s*за\b)' => 'Клиника',
        'рехаб\.' => 'Рехабилитација',
    ];

    /** Lower case inside a name (never as its first word). */
    private const FUNCTION_WORDS = ['и', 'за', 'со', 'на', 'во', 'од', 'по', 'до'];

    /**
     * Generic words of a descriptive run: lower case when they follow
     * another generic word or a function word („Здравствен Дом“, „по Општа
     * Стоматологија“), never at the start or inside quotes, so a brand
     * („Нова Медицина“, „Медика Плус“) keeps its capitals.
     */
    private const GENERIC_WORDS = [
        'дом', 'болница', 'клиника', 'ординација', 'поликлиника', 'општа', 'општата', 'медицина', 'стоматологија',
        'специјалистичка', 'специјална', 'специјализирана', 'здравствена', 'здравствен', 'заштита', 'примарна',
        'примарната', 'примарно', 'ниво', 'центар', 'дијализа', 'лабораторија', 'биохемиска', 'медицинска',
        'гинекологија', 'акушерство', 'педијатрија', 'интерна', 'хирургија', 'орална', 'протетика', 'стоматолошка',
        'ортодонција', 'болести', 'дејност', 'проширена', 'институт', 'универзитетска', 'клиничка', 'превенција',
        'лекување', 'рехабилитација', 'дентална', 'детска', 'детски', 'дерматовенерологија', 'физикална',
        'медицинска', 'трудова', 'семејна', 'ургентна', 'итна', 'помош', 'терапија', 'дијагностика', 'установа',
        'приватна', 'јавна', 'психијатрија', 'неврологија', 'кардиологија', 'офталмологија', 'оториноларингологија',
        'радиологија', 'урологија', 'ортопедија', 'трауматологија', 'пулмологија', 'гастроентерологија',
        'ендокринологија', 'нефрологија', 'онкологија', 'хематологија', 'ревматологија', 'алергологија',
        'микробиологија', 'патологија', 'хирургија', 'максилофацијална', 'стоматолошка', 'ортодонтска',
        'пародонтологија', 'ендодонција', 'болни', 'внатрешни', 'очни', 'очна', 'кожни', 'венерични',
        'секундарно', 'терцијарно', 'кардиоваскуларна', 'кардиоваскуларни', 'заболувања', 'хируршки', 'инфективни',
    ];

    /** Names whose town is part of the name (public institutions). */
    private const KEEPS_TOWN = '/^(?:ЈЗУ|ЈУ|Јавна|Здравствен|Општа\s+болница|Клиничка|Универзитетска|Специјална\s+болница|Специјализирана)/iu';

    /** Legal forms and generic words that do not identify an institution by themselves. */
    private const NOT_DISTINCTIVE = ['пзу', 'јзу', 'дооел', 'доо', 'ад', 'зу', 'д-р', 'др', 'с', 'со', 'на', 'по', 'за', 'и', 'од', 'во'];

    public static function clean(string $raw, ?string $town = null): CleanedName
    {
        $changes = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', $raw));

        if ($name !== $raw) {
            $changes[] = 'spacing';
        }

        $glyphs = Homoglyphs::repair($name);

        if ($glyphs['changed']) {
            $changes[] = 'homoglyph';
            $name = $glyphs['text'];
        }

        $step = function (string $category, string $value) use (&$name, &$changes): void {
            $value = trim((string) preg_replace('/\s+/u', ' ', $value));

            if ($value !== $name) {
                $changes[] = $category;
                $name = $value;
            }
        };

        // Capitals from the register.
        $step('casing', TextCase::institution($name));
        $step('quotes', self::quotes($name));
        $step('legal_form', self::legalForm($name));
        $step('abbreviation', self::abbreviations($name));
        $step('casing', self::titles($name));
        $step('casing', self::lowerWords($name));
        $step('town', self::withoutTown($name, $town));

        $uncertain = $glyphs['unresolved'] ? 'mixed_script' : null;

        return new CleanedName($name, array_values(array_unique($changes)), $uncertain);
    }

    /**
     * The key verification compares register names with (case, quotes,
     * punctuation and the cleaning above aside).
     */
    public static function key(string $name, ?string $town = null): string
    {
        return NameKey::sorted(self::clean($name, $town)->value);
    }

    private static function quotes(string $name): string
    {
        // ’’ and ‘‘ are one quote typed as two apostrophes.
        $marked = (string) preg_replace('/(?:’’|‘‘|\'\'|,,|[„“”"«»])/u', "\u{0001}", $name);
        $count = substr_count($marked, "\u{0001}");

        if ($count === 0 || $count % 2 !== 0) {
            return $name;
        }

        $open = true;
        $result = (string) preg_replace_callback('/\x{0001}/u', function () use (&$open): string {
            $mark = $open ? '„' : '“';
            $open = ! $open;

            return $mark;
        }, $marked);

        // „ Име “ → „Име“; a word glued to an opening quote gets its space.
        $result = (string) preg_replace('/„\s+/u', '„', $result);
        $result = (string) preg_replace('/\s+“/u', '“', $result);
        $result = (string) preg_replace('/(?<=\p{L})„/u', ' „', $result);

        return (string) preg_replace('/“(?=\p{L})/u', '“ ', $result);
    }

    private static function legalForm(string $name): string
    {
        $name = (string) preg_replace('/^(?:П\.\s*З\.\s*У\.?|Приватна\s+здравствена\s+установа|Пзу|ПЗУ)\s*[-–—,:]?\s*/iu', 'ПЗУ ', $name);
        $name = (string) preg_replace('/^(?:Ј\.\s*З\.\s*У\.?|Јавна\s+здравствена\s+установа|Јзу|ЈЗУ)\s*[-–—,:]?\s*/iu', 'ЈЗУ ', $name);
        // Legal forms are acronyms wherever they stand („Доо“ → „ДОО“).
        $name = (string) preg_replace_callback('/(?<![\p{L}])(Дооел|Доо|Пзу|Јзу)(?![\p{L}])/u', fn (array $m): string => mb_strtoupper($m[1], 'UTF-8'), $name);
        // „ПЗУ Ординација“ became „ПЗУ ПЗУ…“ only if the source repeated it.
        $name = (string) preg_replace('/^(ПЗУ|ЈЗУ) \1 /u', '$1 ', $name);

        return trim($name);
    }

    private static function abbreviations(string $name): string
    {
        foreach (self::ABBREVIATIONS as $pattern => $full) {
            $name = (string) preg_replace('/(?<![\p{L}])'.$pattern.'\s*/iu', $full.' ', $name);
        }

        // Any other „Аа.Бб“ / „И.Петров“: a space after the dot.
        return (string) preg_replace('/(?<=\p{L})\.(?=\p{Lu})/u', '. ', $name);
    }

    /**
     * „Д-Р“ (TextCase capitalises both halves) and „Др“ / „Др.“ before a
     * name → „д-р“; „Прим.“ / „Проф.“ before it in lower case too, as
     * Macedonian writes titles inside a name.
     */
    private static function titles(string $name): string
    {
        // After an opening quote or bracket the quoted name starts with a
        // capital („Д-р Иван Георгиев“), so only „Д-Р“ is fixed there.
        $name = (string) preg_replace('/(?<![\p{L}„(])(?:Д-Р|Д-р|д-Р|Др\.?|ДР\.?)(?=\s+[\p{Lu}])/u', 'д-р', $name);
        $name = (string) preg_replace('/(?<![\p{L}])(?:Д-Р|Др\.?|ДР\.?)(?=\s+[\p{Lu}])/u', 'Д-р', $name);
        $name = (string) preg_replace('/(?<![\p{L}])Д-Р(?![\p{L}])/u', 'Д-р', $name);
        $name = (string) preg_replace_callback(
            '/(?<![\p{L}„(])(Прим|Проф|Доц|Асс?)\.\s*(?=д-р)/u',
            fn (array $m): string => mb_strtolower($m[1], 'UTF-8').'. ',
            $name,
        );

        // The very first word stays capitalised („Д-р Петров“ as a whole name).
        return (string) preg_replace_callback('/^д-р/u', fn (): string => 'Д-р', $name);
    }

    private static function lowerWords(string $name): string
    {
        $words = explode(' ', $name);
        $inQuotes = false;
        $previousGeneric = false;
        $start = preg_match('/^(?:ПЗУ|ЈЗУ)$/u', $words[0] ?? '') === 1 ? 1 : 0;

        foreach ($words as $index => $word) {
            $opens = str_starts_with($word, '„');
            $closes = str_ends_with($word, '“');
            $bare = mb_strtolower((string) preg_replace('/^[„“(),.\-]+|[„“(),.\-]+$/u', '', $word), 'UTF-8');
            $isFunction = in_array($bare, self::FUNCTION_WORDS, true) && preg_match('/^\p{L}+$/u', $word) === 1;
            $isGeneric = in_array($bare, self::GENERIC_WORDS, true) && preg_match('/^\p{L}+$/u', $word) === 1;

            if ($opens) {
                $inQuotes = true;
            }

            if (! $inQuotes && $index > $start) {
                if ($isFunction || ($isGeneric && $previousGeneric)) {
                    $words[$index] = mb_strtolower($word, 'UTF-8');
                }
            }

            $previousGeneric = ! $inQuotes && ($isGeneric || $isFunction);

            if ($closes) {
                $inQuotes = false;
            }
        }

        return implode(' ', $words);
    }

    /**
     * Drops the facility's own town (or village) from the end of a private
     * institution's name: „ПЗУ Анди Дент Скопје“ → „ПЗУ Анди Дент“, „…
     * С. Калиште Врапчиште“ (village and municipality) → „…“.
     */
    private static function withoutTown(string $name, ?string $town): string
    {
        if ($town === null || trim($town) === '' || preg_match(self::KEEPS_TOWN, $name) === 1
            || preg_match('/(?:^ПЗУ\s+)?(?:Здравствен\s+дом|болница)/iu', $name) === 1) {
            return $name;
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/\s+[-–—]\s+/u', $town) ?: [])));
        $keys = array_map(fn (string $part): string => NameKey::for($part), $parts);
        $keys = array_values(array_filter($keys, fn (string $key): bool => $key !== ''));

        if ($keys === []) {
            return $name;
        }

        $candidate = $name;

        // „… С. Калиште Врапчиште“, „…С.Калиште“: from the village marker on.
        if (preg_match('/^(.*?)[\s,]+[Сс]\.\s*(\p{L}+)(?:[\s\-]+(\p{L}+))?/u', $candidate, $match) === 1
            && (in_array(NameKey::for($match[2]), $keys, true) || in_array(NameKey::for($match[2].' '.($match[3] ?? '')), $keys, true))) {
            $candidate = $match[1];
        }

        // „… Скопје“, „…, Скопје“, „… - Скопје“, „… Сарај,Скопје“ (one town word or two).
        for ($i = 0; $i < 2; $i++) {
            foreach ([2, 1] as $size) {
                if (preg_match('/^(.*\S)[\s,\-–—]+((?:\S+\s+){'.($size - 1).'}\S+)$/u', $candidate, $match) === 1
                    && ! str_contains($match[2], '“') && ! str_contains($match[2], '„')
                    && in_array(NameKey::for($match[2]), $keys, true)) {
                    $candidate = $match[1];

                    break;
                }
            }
        }

        $candidate = (string) preg_replace('/[\s,\-–—]+$/u', '', $candidate);

        return self::distinctive($candidate) ? $candidate : $name;
    }

    private static function distinctive(string $name): bool
    {
        $words = array_filter(explode(' ', NameKey::for($name)), fn (string $word): bool => $word !== '');

        foreach ($words as $word) {
            $lower = mb_strtolower($word, 'UTF-8');

            if (! in_array($lower, self::NOT_DISTINCTIVE, true) && ! in_array($lower, self::GENERIC_WORDS, true)) {
                return true;
            }
        }

        return false;
    }
}
