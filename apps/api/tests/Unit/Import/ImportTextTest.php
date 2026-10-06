<?php

namespace Tests\Unit\Import;

use App\Support\Import\NameKey;
use App\Support\Import\TextCase;
use App\Support\Import\Website\SpecialtyText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportTextTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function websiteSpecialties(): array
    {
        return [
            'rank + field' => ['Специјалист по општа хирургија', ['ОПШТА ХИРУРГИЈА']],
            'two ranks' => ['Специјалист по општа хирургија; супспецијалист по пластична и реконструктивна хирургија', ['ПЛАСТИЧНА И РЕКОНСТРУКТИВНА ХИРУРГИЈА']],
            'noun compound' => ['Специјалист хирург-уролог', ['УРОЛОГИЈА']],
            'practitioner noun' => ['Офталмолог', ['ОФТАЛМОЛОГИЈА']],
            'parenthetical' => ['Кардиологија (супспецијалист)', ['КАРДИОЛОГИЈА']],
            'field with и is not split' => ['Гинекологија и акушерство', ['ГИНЕКОЛОГИЈА И АКУШЕРСТВО']],
            'rank only' => ['Специјализант', []],
            'job title only' => ['Шеф на оддел', []],
            'doctor of general medicine' => ['Доктор по општа медицина', ['ОПШТА МЕДИЦИНА']],
            'latin look-alikes' => ['ОПШТA СТОМАТОЛОГИЈА', ['ОПШТА СТОМАТОЛОГИЈА']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('websiteSpecialties')]
    public function test_website_specialty_text_reduces_to_catalogue_wordings(string $text, array $expected): void
    {
        $this->assertSame($expected, SpecialtyText::wordings($text));
    }

    public function test_name_keys(): void
    {
        $this->assertSame('АНА ПЕТРОВА ИЛИЕВСКА', NameKey::for('Д-р Ана Петрова-Илиевска'));
        $this->assertSame('АНА ПЕТРОВА ИЛИЕВСКА', NameKey::for('ana petrova ilievska'));
        $this->assertSame('ИВАН ПЕТРОВ', NameKey::for('Проф. д-р ИВАН  ПЕТРОВ'));
        $this->assertSame(NameKey::sorted('Петров Иван'), NameKey::sorted('Иван Петров'));
        // Latin look-alikes inside a Cyrillic word.
        $this->assertSame('ПЕТРОВ', NameKey::for('ПЕТРOВ'));
        // A bare "Др" could be a name; only the punctuated title is dropped.
        $this->assertSame('ДРАГАН ПЕТРОВ', NameKey::for('Драган Петров'));
    }

    public function test_register_casing(): void
    {
        $this->assertSame('Марија Петровска-Илиевска', TextCase::person('МАРИЈА ПЕТРОВСКА-ИЛИЕВСКА'));
        $this->assertSame('ЈЗУ Универзитетска Клиника за Неврологија', TextCase::institution('ЈЗУ УНИВЕРЗИТЕТСКА КЛИНИКА ЗА НЕВРОЛОГИЈА'));
        $this->assertSame('ПЗУ Зегин Медика ДООЕЛ Скопје', TextCase::institution('ПЗУ ЗЕГИН МЕДИКА ДООЕЛ СКОПЈЕ'));
        // Mixed case is left as written.
        $this->assertSame('Клиника Жан Митрев', TextCase::institution('Клиника Жан Митрев'));
    }
}
