<?php

namespace Tests\Unit\Licences;

use App\Support\Licences\KomoraLicenceListParser;
use App\Support\Licences\ParsedLicenceRow;
use PHPUnit\Framework\TestCase;
use Tests\Support\KomoraListPdf;

/**
 * The Лекарска комора list PDFs (Excel exports). All names are invented.
 */
class KomoraLicenceListParserTest extends TestCase
{
    private const HEADER = "Име и презиме\tТип на специјализација Датум на важност Број на лиценца";

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/komora-parser-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);

        parent::tearDown();
    }

    private function pdf(array $pages): string
    {
        $path = $this->directory.'/list.pdf';
        file_put_contents($path, KomoraListPdf::make($pages));

        return $path;
    }

    /**
     * @param  list<ParsedLicenceRow>  $rows
     * @return list<array{0: string, 1: string|null, 2: string, 3: string, 4: string}>
     */
    private function flat(array $rows): array
    {
        return array_map(fn (ParsedLicenceRow $row): array => [
            $row->fullName, $row->specialty, $row->validUntil->format('d.m.Y'), $row->licenceNumber, $row->sourceReference,
        ], $rows);
    }

    public function test_it_reads_a_generated_list_pdf_with_wrapped_cells_and_repeated_headers(): void
    {
        $path = $this->pdf([
            [
                ['name' => 'АНА ТЕСТОВСКА', 'specialty' => 'кардиологија', 'date' => '01.02.2030', 'number' => '0000001'],
                ['name' => 'ЃОРЃИ ЏЕЏОВСКИ-ЌОСЕВСКИ', 'specialty' => ['анестезиологија со интензивно', 'лекување'], 'date' => '15.03.2031', 'number' => '0000002'],
            ],
            [
                ['name' => 'ЉУБИЦА ЊЕГОВСКА ЅВЕЗДОВСКА', 'specialty' => 'доктор на медицина во ПЗЗ', 'date' => '29.05.2023', 'number' => '0000003'],
            ],
        ]);

        $result = (new KomoraLicenceListParser)->parsePdf($path, 'А-В');

        $this->assertSame([], $result->failures);
        $this->assertSame([
            ['АНА ТЕСТОВСКА', 'кардиологија', '01.02.2030', '0000001', 'А-В#p1'],
            ['ЃОРЃИ ЏЕЏОВСКИ-ЌОСЕВСКИ', 'анестезиологија со интензивно лекување', '15.03.2031', '0000002', 'А-В#p1'],
            ['ЉУБИЦА ЊЕГОВСКА ЅВЕЗДОВСКА', 'доктор на медицина во ПЗЗ', '29.05.2023', '0000003', 'А-В#p2'],
        ], $this->flat($result->rows));
    }

    public function test_column_separators_may_be_tabs_or_single_spaces(): void
    {
        $result = (new KomoraLicenceListParser)->parsePages([implode("\n", [
            self::HEADER,
            "МАРТА ПРИМЕРОВСКА\tпедијатрија\t11.11.2031 0000010",
            "ИВО ОГЛЕДОВСКИ семејна медицина 12.12.2032\t0000011",
            "ЕЛЕНА ПРИМЕРОВСКА - ОГЛЕДОВСКА\tинтерна медицина\t01.01.2029\t0000012",
            "САШО ЗАЛЕПЕНОВСКИневрологија\t02.02.2028 0000013",
        ])], 'Г-Ж');

        $this->assertSame([], $result->failures);
        $this->assertSame([
            ['МАРТА ПРИМЕРОВСКА', 'педијатрија', '11.11.2031', '0000010', 'Г-Ж#p1'],
            ['ИВО ОГЛЕДОВСКИ', 'семејна медицина', '12.12.2032', '0000011', 'Г-Ж#p1'],
            ['ЕЛЕНА ПРИМЕРОВСКА-ОГЛЕДОВСКА', 'интерна медицина', '01.01.2029', '0000012', 'Г-Ж#p1'],
            ['САШО ЗАЛЕПЕНОВСКИ', 'неврологија', '02.02.2028', '0000013', 'Г-Ж#p1'],
        ], $this->flat($result->rows));
    }

    public function test_a_name_wrapped_at_its_hyphen_is_joined_without_a_space(): void
    {
        $result = (new KomoraLicenceListParser)->parsePages([implode("\n", [
            'ВЕСНА ДОЛГОПРЕЗИМЕНОВСКА-',
            'ПРИМЕРОВСКА',
            'социјална медицина со организација ',
            'на здр. дејност',
            '03.03.2033 0000020',
        ])], 'З-Љ');

        $this->assertSame([
            ['ВЕСНА ДОЛГОПРЕЗИМЕНОВСКА-ПРИМЕРОВСКА', 'социјална медицина со организација на здр. дејност', '03.03.2033', '0000020', 'З-Љ#p1'],
        ], $this->flat($result->rows));
    }

    public function test_a_row_split_by_a_page_break_carries_over_past_the_repeated_header(): void
    {
        $result = (new KomoraLicenceListParser)->parsePages([
            self::HEADER."\nПЕТАР ПРЕЛОМОВСКИ\nанестезиологија со интензивно",
            self::HEADER."\nлекување\n04.04.2034 0000030\nНИНА СЛЕДНОВСКА\tурологија\t05.05.2035 0000031",
        ], 'М-Р');

        $this->assertSame([], $result->failures);
        $this->assertSame([
            ['ПЕТАР ПРЕЛОМОВСКИ', 'анестезиологија со интензивно лекување', '04.04.2034', '0000030', 'М-Р#p1'],
            ['НИНА СЛЕДНОВСКА', 'урологија', '05.05.2035', '0000031', 'М-Р#p2'],
        ], $this->flat($result->rows));
    }

    public function test_short_licence_numbers_are_zero_padded(): void
    {
        $result = (new KomoraLicenceListParser)->parsePages(["ТЕА КРАТКОВСКА\tрадиологија\t06.06.2036 001342"], 'С-Ш');

        $this->assertSame('0001342', $result->rows[0]->licenceNumber);
    }

    public function test_unreadable_fragments_are_counted_without_their_text_and_never_merged_into_the_next_row(): void
    {
        $result = (new KomoraLicenceListParser)->parsePages([implode("\n", [
            // A row whose licence cell is empty.
            "БЕЗБРОЈ БЕЗБРОЈОВСКИ\tпсихијатрија\t07.07.2027",
            "ДОБАР РЕДОВСКИ\tофталмологија\t08.08.2028 0000040",
            // An impossible date.
            "ЛОШ ДАТУМОВСКИ\tхематологија\t31.02.2029 0000041",
            // A wrapped name line read after the specialty.
            "ИЗМЕШАН\tпедијатрија",
            'ИЗМЕШАНОВСКИ',
            '09.09.2029 0000042',
            // Five lines before a date: not one row.
            'ЕДЕН', 'ДВА', 'ТРИ', 'ЧЕТИРИ', 'ПЕТ',
            '10.10.2030 0000043',
            'ОСТАТОК БЕЗ РЕД',
        ])], 'X');

        $this->assertSame([['ДОБАР РЕДОВСКИ', 'офталмологија', '08.08.2028', '0000040', 'X#p1']], $this->flat($result->rows));
        $this->assertSame([
            'row without a licence number',
            'invalid date',
            'name and specialty interleaved',
            'too many lines for one row',
            'text after the last row',
        ], array_column($result->failures, 'reason'));

        foreach ($result->failures as $failure) {
            $this->assertSame('X#p1', $failure['reference']);
        }
    }

    public function test_a_file_that_is_not_a_pdf_is_one_failure(): void
    {
        $path = $this->directory.'/broken.pdf';
        file_put_contents($path, 'not a pdf');

        $result = (new KomoraLicenceListParser)->parsePdf($path, 'А-В');

        $this->assertSame([], $result->rows);
        $this->assertCount(1, $result->failures);
        $this->assertStringStartsWith('unreadable PDF', $result->failures[0]['reason']);
    }
}
