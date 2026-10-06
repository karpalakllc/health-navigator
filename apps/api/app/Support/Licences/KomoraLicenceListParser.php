<?php

namespace App\Support\Licences;

use Carbon\CarbonImmutable;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads the Лекарска комора „Листа на доктори со важечки лиценци“ PDFs:
 * Excel exports with four columns — „Име и презиме“ | „Тип на
 * специјализација“ | „Датум на важност“ | „Број на лиценца“.
 *
 * The extracted text is not a clean table, so rows are anchored on what every
 * row ends with — a date and a licence number — and everything read since
 * the previous anchor is the name and the specialty:
 * - a long specialty (or name) wraps onto the next line(s) before the anchor;
 * - the column separator is a tab or just a space, so the name is the leading
 *   run of upper-case words and the specialty starts at the first word with a
 *   lower-case letter („доктор на медицина во ПЗЗ“ ends upper case, fine);
 * - the header row repeats on every page and is dropped;
 * - a row that spans a page break carries over.
 * A fragment that cannot be read is counted with its page, never guessed.
 */
final class KomoraLicenceListParser
{
    /**
     * The list is text only: images are not kept, and a stream may not
     * decode to more than 64 MB (a hostile or broken PDF cannot exhaust
     * memory).
     */
    public static function pdfConfig(): Config
    {
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        return $config;
    }

    /** A row ends with „Датум на важност“ then „Број на лиценца“ (tab or space between). */
    private const ROW_END = '/(?:^|\s)(\d{1,2})\.(\d{1,2})\.(\d{4})\.?\s+(\d{4,8})\s*$/u';

    /** A line ending with a date but no licence number: a row with an empty cell. */
    private const DATE_END = '/(?:^|\s)\d{1,2}\.\d{1,2}\.\d{4}\.?\s*$/u';

    /** Header labels; the header row repeats on every page. */
    private const HEADER_LABELS = ['ИМЕ И ПРЕЗИМЕ', 'ТИП НА СПЕЦИЈАЛИЗАЦИЈА', 'ДАТУМ НА ВАЖНОСТ', 'БРОЈ НА ЛИЦЕНЦА'];

    /** Upper-case words allowed inside a specialty (abbreviations). */
    private const SPECIALTY_ABBREVIATIONS = ['ПЗЗ', 'ПЗЗ.'];

    /** Real rows wrap onto three lines at most (name, two specialty lines). */
    private const MAX_FRAGMENTS = 4;

    public function parsePdf(string $path, string $label): LicenceParseResult
    {
        try {
            $document = (new Parser([], self::pdfConfig()))->parseFile($path);
            $pages = array_map(
                static fn ($page): string => $page->getText(),
                array_values($document->getPages()),
            );
        } catch (Throwable $exception) {
            return new LicenceParseResult([], [[
                'reference' => $label,
                'reason' => 'unreadable PDF: '.class_basename($exception),
            ]]);
        }

        return $this->parsePages($pages, $label);
    }

    /**
     * @param  list<string>  $pages  extracted text of each page, in order
     */
    public function parsePages(array $pages, string $label): LicenceParseResult
    {
        $rows = [];
        $failures = [];
        $buffer = [];
        $bufferPage = 1;

        foreach ($pages as $index => $text) {
            $page = $index + 1;

            foreach (preg_split('/\R/u', $text) ?: [] as $rawLine) {
                $line = $this->clean($rawLine);

                if ($line === '' || $this->isHeader($line)) {
                    continue;
                }

                if (preg_match(self::ROW_END, $line, $match, PREG_OFFSET_CAPTURE) === 1) {
                    $head = trim(substr($line, 0, $match[0][1]));
                    $fragments = $head === '' ? $buffer : [...$buffer, $head];
                    $reference = $label.'#p'.($buffer === [] ? $page : $bufferPage);
                    $buffer = [];

                    $result = $this->row(
                        $fragments,
                        (int) $match[1][0],
                        (int) $match[2][0],
                        (int) $match[3][0],
                        $match[4][0],
                        $reference,
                    );

                    if (is_string($result)) {
                        $failures[] = ['reference' => $reference, 'reason' => $result];
                    } else {
                        $rows[] = $result;
                    }

                    continue;
                }

                if (preg_match(self::DATE_END, $line) === 1) {
                    // A row whose licence cell is empty: not a usable row, and
                    // carrying it over would corrupt the next one.
                    $failures[] = ['reference' => $label.'#p'.$page, 'reason' => 'row without a licence number'];
                    $buffer = [];

                    continue;
                }

                if ($buffer === []) {
                    $bufferPage = $page;
                }

                $buffer[] = $line;
            }
        }

        if ($buffer !== []) {
            $failures[] = ['reference' => $label.'#p'.$bufferPage, 'reason' => 'text after the last row'];
        }

        return new LicenceParseResult($rows, $failures);
    }

    /**
     * @param  list<string>  $fragments  the lines of one row before its date
     */
    private function row(array $fragments, int $day, int $month, int $year, string $number, string $reference): ParsedLicenceRow|string
    {
        if ($fragments === []) {
            return 'date and licence number without a name';
        }

        if (count($fragments) > self::MAX_FRAGMENTS) {
            return 'too many lines for one row';
        }

        if (! checkdate($month, $day, $year)) {
            return 'invalid date';
        }

        [$name, $specialty] = $this->splitNameAndSpecialty($this->join($fragments));

        if ($name === '' || preg_match('/\p{L}{2,}/u', $name) !== 1) {
            return 'no name';
        }

        if ($specialty !== null && $this->hasStrayUpperCase($specialty)) {
            return 'name and specialty interleaved';
        }

        return new ParsedLicenceRow(
            fullName: $name,
            specialty: $specialty,
            validUntil: CarbonImmutable::create($year, $month, $day)->startOfDay(),
            licenceNumber: str_pad(ltrim($number, ' '), 7, '0', STR_PAD_LEFT),
            sourceReference: $reference,
        );
    }

    /**
     * Wrapped lines: a line broken after a hyphen continues the same word.
     *
     * @param  list<string>  $fragments
     */
    private function join(array $fragments): string
    {
        $text = '';

        foreach ($fragments as $fragment) {
            $text = $text === '' || str_ends_with($text, '-')
                ? $text.$fragment
                : $text.' '.$fragment;
        }

        // "ПЕТРОВА - ИЛИЕВСКА" is the same double surname as "ПЕТРОВА-ИЛИЕВСКА".
        return (string) preg_replace('/\s*-\s*(?=\p{Lu})/u', '-', $text);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitNameAndSpecialty(string $text): array
    {
        $name = [];
        $specialty = [];

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($specialty === [] && preg_match('/\p{Ll}/u', $word) === 1) {
                // Cells glued without a separator: "ПЕТРОВАинтерна".
                if (preg_match('/^(\p{Lu}[\p{Lu}\-]*\p{Lu})(\p{Ll}.*)$/u', $word, $glued) === 1) {
                    $name[] = $glued[1];
                    $specialty[] = $glued[2];

                    continue;
                }

                $specialty[] = $word;

                continue;
            }

            if ($specialty === []) {
                $name[] = $word;
            } else {
                $specialty[] = $word;
            }
        }

        $specialtyText = trim(implode(' ', $specialty));

        return [trim(implode(' ', $name)), $specialtyText === '' ? null : $specialtyText];
    }

    /**
     * An upper-case word inside the specialty is a wrapped name line read out
     * of order, not part of the specialty.
     */
    private function hasStrayUpperCase(string $specialty): bool
    {
        foreach (preg_split('/\s+/u', $specialty) ?: [] as $word) {
            if (preg_match('/^\p{Lu}{2,}[\p{Lu}\-.]*$/u', $word) === 1
                && ! in_array($word, self::SPECIALTY_ABBREVIATIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private function isHeader(string $line): bool
    {
        $upper = mb_strtoupper($line, 'UTF-8');

        foreach (self::HEADER_LABELS as $label) {
            if (str_contains($upper, $label)) {
                return true;
            }
        }

        return false;
    }

    private function clean(string $line): string
    {
        // Non-breaking and zero-width spaces, soft hyphens from the PDF text layer.
        $line = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $line);
        $line = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}", "\u{00AD}"], '', $line);

        return trim((string) preg_replace('/[ \t]+/u', ' ', $line));
    }
}
