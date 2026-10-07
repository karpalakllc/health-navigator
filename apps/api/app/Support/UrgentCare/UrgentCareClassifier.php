<?php

namespace App\Support\UrgentCare;

/**
 * Reads what the public sources say about a facility — ФЗОМ work units, the
 * departments and hours text on its own website, its name — and lists the
 * urgent-care evidence in it (docs/urgent-care.md § Data).
 *
 * Two strengths:
 * - `strong`: the wording names the service itself („Ургентен центар“,
 *   „Служба за итна медицинска помош“, „итна стоматолошка помош“, „Ургентен
 *   центар 24/7“). The deriver may switch the flag on.
 * - `candidate`: suggests it, but could be a ward or the whole hospital
 *   („Оддел за ургентна психијатрија“, „Општа болница“, „Секој ден 24/7“).
 *   Never switched on automatically: staff confirm it in the admin.
 *
 * Opening hours are never inferred: only a clause that ties round-the-clock
 * hours to the urgent service in the institution's own words counts.
 */
final class UrgentCareClassifier
{
    public const FLAG_ED = 'ed';

    public const FLAG_EMS = 'ems';

    public const FLAG_CLINIC = 'clinic';

    public const FLAG_DENTAL = 'dental';

    public const FLAG_OPEN_24H = 'open24';

    public const STRONG = 'strong';

    public const CANDIDATE = 'candidate';

    /** Evidence flag => facilities column. */
    public const COLUMNS = [
        self::FLAG_ED => 'has_emergency_services',
        self::FLAG_EMS => 'has_emergency_medical_service',
        self::FLAG_CLINIC => 'has_on_duty_clinic',
        self::FLAG_DENTAL => 'has_dental_emergency',
        self::FLAG_OPEN_24H => 'is_open_24h',
    ];

    private const ED_STRONG = [
        '/ургентен(\s+хируршки)?\s+центар/u',
        '/ургентна\s+амбуланта/u',
        '/^ургентни\s+состојби$/u',
        '/^ургентна\s+медицина$/u',
    ];

    private const EMS = '/итна\s+(медицинска\s+)?помош/u';

    private const DENTAL = '/(итна|итни|дежурна)\s+стоматолош|стоматолош\S*\s+(итна|дежурна)/u';

    private const DENTAL_NAME = '/стоматолош|дент|забн/u';

    private const ON_DUTY = '/дежурна\s+(амбуланта|ординација|служба)/u';

    // No \b: without (*UCP) it does not see Cyrillic letters as word characters.
    private const URGENT_WORD = '/ургент|итн[аи](?![\p{L}])|дежурн/u';

    private const ROUND_THE_CLOCK = '/24\s*\/\s*7|24\s*час|24\s*ч(?![\p{L}])|00\s*[–—-]\s*24/u';

    private const GENERAL_HOSPITAL = '/општа\s+болница|клиничка\s+болница|клинички\s+центар|градска\s+општа/u';

    /**
     * @param  list<array{source: string, kind: string, text: string}>  $texts  kind: work_unit | department | hours
     * @return list<array{flag: string, strength: string, source: string, kind: string, text: string}>
     */
    public static function classify(string $facilityName, array $texts): array
    {
        $name = self::fold($facilityName);
        $isDentalPractice = (bool) preg_match(self::DENTAL_NAME, $name);
        $isPublic = str_starts_with($name, 'јзу');
        $items = [];

        foreach ($texts as $entry) {
            $text = self::fold($entry['text']);

            if ($text === '') {
                continue;
            }

            $add = function (string $flag, string $strength) use (&$items, $entry): void {
                $items[] = [
                    'flag' => $flag,
                    'strength' => $strength,
                    'source' => $entry['source'],
                    'kind' => $entry['kind'],
                    'text' => mb_substr(trim($entry['text']), 0, 200),
                ];
            };

            if ($entry['kind'] === 'hours') {
                foreach (preg_split('/[;\n]/u', $text) ?: [] as $clause) {
                    self::classifyHoursClause(trim($clause), $add);
                }

                continue;
            }

            if (preg_match(self::DENTAL, $text)) {
                $add(self::FLAG_DENTAL, self::STRONG);

                continue;
            }

            if ($isDentalPractice && preg_match('/^(дежурна\s+служба|итни\s+случаи)$/u', $text)) {
                $add(self::FLAG_DENTAL, $isPublic ? self::STRONG : self::CANDIDATE);

                continue;
            }

            if (preg_match(self::EMS, $text)) {
                $add(self::FLAG_EMS, self::STRONG);

                continue;
            }

            if (self::matchesAny(self::ED_STRONG, $text)) {
                $add(self::FLAG_ED, self::STRONG);

                continue;
            }

            if (preg_match(self::ON_DUTY, $text)) {
                $add(self::FLAG_CLINIC, self::CANDIDATE);

                continue;
            }

            if (preg_match('/ургент|итна\s+(гинеколог|трауматолог)/u', $text)) {
                $add(self::FLAG_ED, self::CANDIDATE);
            }
        }

        $hasStrongEd = array_filter($items, fn (array $item): bool => $item['flag'] === self::FLAG_ED && $item['strength'] === self::STRONG) !== [];

        if (! $hasStrongEd && preg_match(self::GENERAL_HOSPITAL, $name)) {
            $items[] = [
                'flag' => self::FLAG_ED,
                'strength' => self::CANDIDATE,
                'source' => 'directory',
                'kind' => 'name',
                'text' => mb_substr($facilityName, 0, 200),
            ];
        }

        return self::unique($items);
    }

    /**
     * The flags with strong evidence.
     *
     * @param  list<array{flag: string, strength: string, source: string, kind: string, text: string}>  $items
     * @return list<string>
     */
    public static function strongFlags(array $items): array
    {
        $flags = [];

        foreach ($items as $item) {
            if ($item['strength'] === self::STRONG) {
                $flags[$item['flag']] = true;
            }
        }

        return array_keys($flags);
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function classifyHoursClause(string $clause, callable $add): void
    {
        if ($clause === '' || ! preg_match(self::ROUND_THE_CLOCK, $clause)) {
            return;
        }

        if (! preg_match(self::URGENT_WORD, $clause)) {
            // „Секој ден 24/7“, „Болницата работи 24 часа“: not said of the
            // urgent service.
            $add(self::FLAG_OPEN_24H, self::CANDIDATE);

            return;
        }

        if (preg_match('/стоматолош/u', $clause)) {
            $add(self::FLAG_DENTAL, self::STRONG);
            $add(self::FLAG_OPEN_24H, self::STRONG);

            return;
        }

        if (preg_match(self::EMS, $clause)) {
            $add(self::FLAG_EMS, self::STRONG);
            $add(self::FLAG_OPEN_24H, self::STRONG);

            return;
        }

        if (preg_match('/ургент/u', $clause)) {
            // „Ургентен центар 24/7“, „ургентна служба 00–24 ч.“
            $add(self::FLAG_ED, preg_match('/ургентен\s+центар|ургентна\s+(служба|медицина)/u', $clause) ? self::STRONG : self::CANDIDATE);
            $add(self::FLAG_OPEN_24H, self::STRONG);

            return;
        }

        // „24ч дежурна служба“: on duty, but of what is not said.
        $add(self::FLAG_CLINIC, self::CANDIDATE);
        $add(self::FLAG_OPEN_24H, self::CANDIDATE);
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function matchesAny(array $patterns, string $text): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        // „Ургентен центар — Оддел (раководител)“: the role suffix is noise.
        $text = (string) preg_replace('/\s*\((раководител|шеф)[^)]*\)\s*$/u', '', $text);
        $text = (string) preg_replace('/\s*[—–-]\s*(раководител|шеф)\s*$/u', '', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param  list<array{flag: string, strength: string, source: string, kind: string, text: string}>  $items
     * @return list<array{flag: string, strength: string, source: string, kind: string, text: string}>
     */
    private static function unique(array $items): array
    {
        $seen = [];

        foreach ($items as $item) {
            $seen[implode('|', [$item['flag'], $item['strength'], $item['source'], $item['kind'], mb_strtolower($item['text'])])] ??= $item;
        }

        return array_values($seen);
    }
}
