<?php

namespace Tests\Support;

/**
 * Builds a small PDF laid out like the Лекарска комора licence list (an
 * Excel 2021 export): a header row repeated at the top of every page, then
 * one table row per licence with four cells — name, specialty, expiry date,
 * licence number — each drawn as its own text object at its column's x.
 *
 * Text is written the way Excel writes it: a Type0 font with Identity-H
 * encoding and a ToUnicode map, so a parser has to go through the CMap to
 * get Cyrillic back. Like the real files, a long specialty wraps inside its
 * cell: the name is drawn on the row's first line, the specialty's lines
 * below it, and the date and number on the last line.
 *
 * Only synthetic names belong in tests; nothing here ever reads a real list.
 */
final class KomoraListPdf
{
    private const HEADER = ['Име и презиме', 'Тип на специјализација', 'Датум на важност', 'Број на лиценца'];

    private const COLUMNS = [52.32, 232.61, 431.38, 514.2];

    private const LINE = 9.84;

    /**
     * @param  list<list<array{name: string|list<string>, specialty: string|list<string>, date: string, number: string}>>  $pages
     *                                                                                                                             rows per page; a list of strings is a cell wrapped onto several lines
     */
    public static function make(array $pages): string
    {
        $objects = [];
        // 1 catalog, 2 pages, 3 font (Type0), 4 descendant, 5 ToUnicode, then page/content pairs.
        $pageIds = [];
        $next = 6;

        foreach ($pages as $rows) {
            $pageId = $next++;
            $contentId = $next++;
            $pageIds[] = $pageId;
            $stream = self::content($rows);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.32 841.92] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $kids = implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageIds));
        $cmap = self::toUnicode();

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count ".count($pageIds).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEE+Calibri /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 5 0 R >>';
        $objects[4] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ABCDEE+Calibri /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 >>';
        $objects[5] = '<< /Length '.strlen($cmap)." >>\nstream\n{$cmap}\nendstream";
        ksort($objects);

        $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * @param  list<array{name: string|list<string>, specialty: string|list<string>, date: string, number: string}>  $rows
     */
    private static function content(array $rows): string
    {
        $y = 774.84;
        $out = '';

        foreach (self::HEADER as $index => $label) {
            $out .= self::text(self::COLUMNS[$index] + 40, $y, $label);
        }

        foreach ($rows as $row) {
            $name = (array) $row['name'];
            $specialty = (array) $row['specialty'];
            $y -= 14.88;

            if (count($name) === 1 && count($specialty) === 1) {
                $out .= self::text(self::COLUMNS[0], $y, $name[0]);
                $out .= self::text(self::COLUMNS[1], $y, $specialty[0]);
                $out .= self::text(self::COLUMNS[2], $y + 0.24, $row['date']);
                $out .= self::text(self::COLUMNS[3], $y + 0.24, $row['number']);

                continue;
            }

            // A wrapped row: the name first, then the specialty's lines, then
            // the date and number on a line of their own.
            foreach ($name as $line) {
                $out .= self::text(self::COLUMNS[0], $y, $line);
                $y -= self::LINE;
            }

            foreach ($specialty as $line) {
                $out .= self::text(self::COLUMNS[1], $y, $line);
                $y -= self::LINE;
            }

            $out .= self::text(self::COLUMNS[2], $y, $row['date']);
            $out .= self::text(self::COLUMNS[3], $y, $row['number']);
        }

        return $out;
    }

    private static function text(float $x, float $y, string $text): string
    {
        $hex = '';

        foreach (mb_str_split($text) as $char) {
            $hex .= sprintf('%04X', mb_ord($char, 'UTF-8'));
        }

        return sprintf("BT\n/F1 8.04 Tf\n1 0 0 1 %.2f %.2f Tm\n<%s> Tj\nET\n", $x, $y, $hex);
    }

    /**
     * Glyph ids are the code points themselves; the map says so for Latin,
     * Cyrillic and general punctuation.
     */
    private static function toUnicode(): string
    {
        $ranges = '';

        foreach ([0x00, 0x04, 0x20] as $high) {
            $ranges .= sprintf("<%02X00> <%02XFF> <%02X00>\n", $high, $high, $high);
        }

        return "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
            ."/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            ."/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            ."1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
            ."3 beginbfrange\n{$ranges}endbfrange\n"
            ."endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    }
}
