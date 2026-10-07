<?php

namespace App\Support\Import\Pharmacies;

use App\Models\PharmacyDutyShift;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/**
 * Reads ФЗОМ's „Распоред на дежурни аптеки“ (.xlsx, one sheet). The layout
 * seen in 2026:
 *
 *   ОКТОМВРИ,2026
 *   Град/Населено место | | Назив на ПЗУ аптека | Датум … | Телефон | Начин на работа …
 *   СКОПЈЕ                                            ← a town heading
 *   1 | СКОПЈЕ-АЕРОДРОМ | ВИОЛА 7 ТОБАКО | 01.10-31.10.2026 | 02/2 466-103 | 24/7 …
 *
 * Every town writes its dates its own way; parseDays() knows the forms seen
 * (a spreadsheet date, „01.10.2026“, „01-10-2026“, ranges with „-“, „—“,
 * „до“, „од … до …“, „08/09-10-2026“, day lists „1,2,3,4“ and „6.14.22.30.“,
 * a bare day „7“). A row it cannot read is reported, never guessed.
 *
 * Only the numbers of the phone column are kept: some towns put the
 * pharmacist's name there.
 */
final class OnDutyScheduleParser
{
    private const MONTHS = [
        'јануари' => 1, 'јануар' => 1, 'февруари' => 2, 'февруар' => 2, 'март' => 3, 'април' => 4,
        'мај' => 5, 'јуни' => 6, 'јули' => 7, 'август' => 8, 'септември' => 9, 'октомври' => 10,
        'ноември' => 11, 'декември' => 12,
    ];

    /**
     * @param  string|null  $expectedMonth  YYYY-MM, used when the sheet has no month heading
     * @return array{month: string, rows: list<array{line: int, town: string, municipality: string|null, name: string, days: list<int>, phone: string|null, mode: string, hours_text: string|null, address: string|null}>, problems: list<array{line: int, reason: string, text: string}>}
     */
    public function parse(string $path, ?string $expectedMonth = null): array
    {
        $reader = new Reader;
        $reader->open($path);

        $month = null;
        $sectionTown = null;
        $rows = [];
        $problems = [];
        $line = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(
                        fn ($cell) => $cell->getValue() instanceof DateTimeInterface ? $cell->getValue() : trim((string) $cell->getValue()),
                        $row->getCells(),
                    );
                    $first = is_string($cells[0] ?? null) ? $cells[0] : '';

                    if ($month === null && ($found = self::monthFromHeadingCell($cells[0] ?? null)) !== null) {
                        $month = $found;

                        continue;
                    }

                    if (! is_numeric($first)) {
                        // A town heading („БИТОЛА“) or the column titles.
                        $rest = array_filter(array_slice($cells, 1, 5), fn ($value) => $value !== '');

                        if ($first !== '' && $rest === [] && mb_strlen($first) <= 40) {
                            $sectionTown = $first;
                        }

                        continue;
                    }

                    $month ??= $expectedMonth;

                    if ($month === null) {
                        throw new RuntimeException('The schedule has no month heading; pass --month.');
                    }

                    $townRaw = is_string($cells[1] ?? null) && $cells[1] !== '' ? $cells[1] : (string) $sectionTown;
                    $name = is_string($cells[2] ?? null) ? $cells[2] : '';

                    if ($townRaw === '' || $name === '') {
                        $problems[] = ['line' => $line, 'reason' => 'missing_town_or_name', 'text' => self::rowText($cells)];

                        continue;
                    }

                    $days = self::parseDays($cells[3] ?? '', $month);

                    if ($days === null) {
                        $problems[] = ['line' => $line, 'reason' => 'unreadable_date', 'text' => self::rowText($cells)];

                        continue;
                    }

                    [$town, $municipality] = OnDutyNames::town($townRaw);
                    [$mode, $hours, $address] = self::mode(is_string($cells[5] ?? null) ? $cells[5] : '');

                    $rows[] = [
                        'line' => $line,
                        'town' => $town,
                        'municipality' => $municipality,
                        'name' => OnDutyNames::displayName($name),
                        'days' => $days,
                        'phone' => self::phones(is_string($cells[4] ?? null) ? $cells[4] : ''),
                        'mode' => $mode,
                        'hours_text' => $hours,
                        'address' => $address,
                    ];
                }

                break; // One sheet.
            }
        } finally {
            $reader->close();
        }

        if ($month === null) {
            throw new RuntimeException('No schedule rows found.');
        }

        if ($expectedMonth !== null && $month !== $expectedMonth) {
            throw new RuntimeException("The file is the schedule for {$month}, not {$expectedMonth}.");
        }

        return ['month' => $month, 'rows' => $rows, 'problems' => $problems];
    }

    /** The first row: „ОКТОМВРИ,2026“, or a spreadsheet date in that month. */
    private static function monthFromHeadingCell(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m');
        }

        return is_string($value) ? self::monthFromHeading($value) : null;
    }

    /** „ОКТОМВРИ,2026“ / „Октомври 2026“ → 2026-10. */
    public static function monthFromHeading(string $text): ?string
    {
        if (preg_match('/^\s*(\p{L}+)\s*[,.\s]\s*(\d{4})\s*$/u', $text, $match) !== 1) {
            return null;
        }

        $number = self::MONTHS[mb_strtolower($match[1], 'UTF-8')] ?? null;

        return $number === null ? null : sprintf('%04d-%02d', (int) $match[2], $number);
    }

    /** The month a link names: „… за 2026 месец Октомври“ or „…-10.2026.xlsx“. */
    public static function monthFromLink(string $text, string $href): ?string
    {
        if (preg_match('/(\d{4})\s+месец\s+(\p{L}+)/u', $text, $match) === 1
            && isset(self::MONTHS[mb_strtolower($match[2], 'UTF-8')])) {
            return sprintf('%04d-%02d', (int) $match[1], self::MONTHS[mb_strtolower($match[2], 'UTF-8')]);
        }

        if (preg_match('/(?<!\d)(\d{2})\.(\d{4})\.xlsx$/i', rawurldecode($href), $match) === 1 && (int) $match[1] >= 1 && (int) $match[1] <= 12) {
            return sprintf('%04d-%02d', (int) $match[2], (int) $match[1]);
        }

        return null;
    }

    /**
     * The days of `month` a date cell covers, or null when it cannot be read.
     *
     * @return list<int>|null
     */
    public static function parseDays(mixed $value, string $month): ?array
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $lastDay = CarbonImmutable::create($year, $monthNumber, 1)->daysInMonth;

        // A date cell without a date format reads as its spreadsheet serial (46303 = 2026-10-08).
        if (is_numeric($value) && (float) $value >= 30000 && (float) $value < 80000) {
            $value = CarbonImmutable::create(1899, 12, 30)->addDays((int) $value);
        }

        if ($value instanceof DateTimeInterface) {
            return (int) $value->format('Y') === $year && (int) $value->format('n') === $monthNumber ? [(int) $value->format('j')] : null;
        }

        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        $text = (string) preg_replace('/^(0д|од)\s+/u', '', $text);
        $text = (string) preg_replace('/\([^)]*\)/u', '', $text);           // „4(недела)“
        $text = (string) preg_replace('/\s*г\.?$/u', '', trim($text));    // „… 2026 г.“
        $text = (string) preg_replace('/(\d)\.\s+(\d{4})/u', '$1.$2', $text); // „18.01. 2026“
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return null;
        }

        // „11.012026“ (a dot missing before the year) is read when the month has two digits.
        $date = '(\d{1,2})[.\-](\d{1,2}|\d{2}(?=\d{4}))[.\-]?(\d{4})\.?';
        // No \b: without (*UCP) it does not see Cyrillic letters as word characters.
        $separator = '\s*(?:-{1,2}|–|—|(?<!\p{L})до(?!\p{L}))\s*';

        $inMonth = function (int $day, int $m, int $y) use ($monthNumber, $year, $lastDay): bool {
            return $m === $monthNumber && $y === $year && $day >= 1 && $day <= $lastDay;
        };

        // A full date to a full date.
        if (preg_match('/^'.$date.$separator.$date.'$/u', $text, $m) === 1) {
            return self::range($year, $monthNumber, [(int) $m[1], (int) $m[2], (int) $m[3]], [(int) $m[4], (int) $m[5], (int) $m[6]]);
        }

        // „01.10-31.10.2026“, „01.10. до 31.10.2026“, „29.12. до 01.01.2026“.
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.?'.$separator.$date.'$/u', $text, $m) === 1) {
            $startYear = (int) $m[2] > (int) $m[4] ? (int) $m[5] - 1 : (int) $m[5];

            return self::range($year, $monthNumber, [(int) $m[1], (int) $m[2], $startYear], [(int) $m[3], (int) $m[4], (int) $m[5]]);
        }

        // One date.
        if (preg_match('/^'.$date.'$/u', $text, $m) === 1) {
            return $inMonth((int) $m[1], (int) $m[2], (int) $m[3]) ? [(int) $m[1]] : null;
        }

        // „08/09-10-2026“: two nights.
        if (preg_match('/^(\d{1,2})\/(\d{1,2})[.\-](\d{1,2})[.\-](\d{4})$/u', $text, $m) === 1) {
            $days = [(int) $m[1], (int) $m[2]];

            return $inMonth($days[0], (int) $m[3], (int) $m[4]) && $inMonth($days[1], (int) $m[3], (int) $m[4]) ? $days : null;
        }

        // Day lists: „1,2,3,4“, „6.14.22.30.“ (three or more), „7“.
        if (preg_match('/^\d{1,2}(?:\s*[,.]\s*\d{1,2})*[.,]?$/u', $text) === 1) {
            $days = array_map('intval', preg_split('/\s*[,.]\s*/u', rtrim($text, '.,')) ?: []);
            $dotted = str_contains($text, '.') && ! str_contains($text, ',');

            // „1.10.“ could be a date without a year: not a list of two days.
            if ($dotted && count($days) === 2) {
                return null;
            }

            foreach ($days as $day) {
                if ($day < 1 || $day > $lastDay) {
                    return null;
                }
            }

            return array_values(array_unique($days));
        }

        return null;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from  day, month, year
     * @param  array{0: int, 1: int, 2: int}  $to
     * @return list<int>|null
     */
    private static function range(int $year, int $month, array $from, array $to): ?array
    {
        if (! checkdate($from[1], $from[0], $from[2]) || ! checkdate($to[1], $to[0], $to[2])) {
            return null;
        }

        $start = CarbonImmutable::create($from[2], $from[1], $from[0]);
        $end = CarbonImmutable::create($to[2], $to[1], $to[0]);

        if ($end->lessThan($start) || $start->diffInDays($end) > 62) {
            return null;
        }

        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($day->year === $year && $day->month === $month) {
                $days[] = $day->day;
            }
        }

        return $days === [] ? null : $days;
    }

    /**
     * „24/7 работно време во аптека“ → all day; „по телефонски повик …“ → on
     * call; times („Од 23:00-07:00“, „8:00 до 21:00“, „После 21 часот“) →
     * hours; anything else is taken for an address (some towns put the
     * street in that column).
     *
     * @return array{0: string, 1: string|null, 2: string|null} mode, hours text, address
     */
    private static function mode(string $text): array
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $text));
        $lower = mb_strtolower($clean, 'UTF-8');

        if ($clean === '') {
            return [PharmacyDutyShift::MODE_UNKNOWN, null, null];
        }

        if (str_contains($lower, '24/7') || str_contains($lower, '00-24') || str_contains($lower, '0-24')) {
            return [PharmacyDutyShift::MODE_ALL_DAY, mb_substr($clean, 0, 255), null];
        }

        if (str_contains($lower, 'повик')) {
            return [PharmacyDutyShift::MODE_ON_CALL, mb_substr($clean, 0, 255), null];
        }

        if (preg_match('/\d{1,2}[:.]\d{2}|после\s+\d|\d\s*(h|ч\b|часот)|\d{1,2}\s*-\s*\d{1,2}\s*(h|ч)/u', $lower) === 1) {
            return [PharmacyDutyShift::MODE_HOURS, mb_substr($clean, 0, 255), null];
        }

        return [PharmacyDutyShift::MODE_UNKNOWN, null, mb_substr($clean, 0, 255)];
    }

    /** The phone numbers of the cell, without any name next to them. */
    private static function phones(string $text): ?string
    {
        preg_match_all('/\+?\d[\d\s\/\-]{4,}\d/u', $text, $matches);
        $numbers = [];

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate) ?? '';

            if (strlen($digits) >= 6) {
                $numbers[] = trim((string) preg_replace('/\s+/', ' ', $candidate));
            }
        }

        return $numbers === [] ? null : mb_substr(implode(', ', array_values(array_unique($numbers))), 0, 255);
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private static function rowText(array $cells): string
    {
        // Columns 0–3 only: never the phone column (it can carry a person's name).
        return mb_substr(implode(' | ', array_map(
            fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value,
            array_slice($cells, 0, 4),
        )), 0, 300);
    }
}
