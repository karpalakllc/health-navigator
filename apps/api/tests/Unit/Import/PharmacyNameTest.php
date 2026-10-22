<?php

namespace Tests\Unit\Import;

use App\Support\Import\Pharmacies\OnDutyLinker;
use App\Support\Import\Pharmacies\PharmacyName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pharmacy branch names from ФЗОМ's register (forms seen in the 2026-10 file).
 */
class PharmacyNameTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string|null, string}>
     */
    public static function names(): array
    {
        return [
            'branch with town' => ['ПЗУ АПТЕКА ЖИВА ФАРМ 2 ВЕЛЕС', 'ПЗУ АПТЕКА ЖИВА ФАРМ', 'Велес', 'Аптека Жива Фарм 2'],
            'branch after ПОДРУЖНИЦА' => ['ПРИВАТНА ЗДРАВСТВЕНА УСТАНОВА-АПТЕКА ЗЕГИН ФАРМ СКОПЈЕ-ПОДРУЖНИЦА АПТЕКА ЗЕГИН ФАРМ УНИВЕРЗАЛНА', 'ПЗУ АПТЕКА ЗЕГИН ФАРМ', 'Скопје - Центар', 'Аптека Зегин Фарм Универзална'],
            'chain name in front of a bare branch' => ['ПЗУ АПТЕКА MОЈА АПТЕКА СКОПЈЕ ПОДРУЖНИЦА ПРИЛЕП 5', 'ПЗУ АПТЕКА МОЈА АПТЕКА', 'Прилеп', 'Моја Аптека Прилеп 5'],
            'chain repeating Аптека' => ['ПЗУ АПТЕКА МОЈА АПТЕКА ОХРИД 4', 'ПЗУ АПТЕКА МОЈА АПТЕКА', 'Охрид', 'Моја Аптека Охрид 4'],
            'organisational unit' => ['ПРИВАТНА ЗДРАВСТВЕНА УСТАНОВА АПТЕКА ВИТАЛЕК ТЕТОВО-ОРГАНИЗАЦИОНА ЕДИНИЦА АПТЕКА ВИТАЛЕК 2 С.МИЛЕТИНО,БРВЕНИЦА', 'ПЗУ АПТЕКА ВИТАЛЕК', 'Милетино', 'Аптека Виталек 2'],
            'legal entity then dash' => ['ПРИВАТНА ЗДРАВСТВЕНА УСТАНОВА-АПТЕКА ЕУРОФАРМ БИТОЛА-АПТЕКА ЕУРОФАРМ ПИРИН СКОПЈЕ', 'ПЗУ АПТЕКА ЕУРОФАРМ БИТОЛА', 'Скопје - Ѓорче Петров', 'Аптека Еурофарм Пирин'],
            'pharmacy station' => ['ПЗУ АПТЕКАРСКА СТАНИЦА ФЛОС ФАРМ ЧАШКА', 'ПЗУ АПТЕКА ФЛОС ФАРМ', 'Чашка', 'Аптекарска станица Флос Фарм'],
            'bare brand' => ['НОВАЛЕК', 'ПЗУ АПТЕКА НОВАЛЕК', 'Куманово', 'Аптека Новалек'],
            'village marker' => ['ПЗУ АПТЕКА НАТУРА ЛЕК С.КРАВАРИ, БИТОЛА', 'ПЗУ АПТЕКА НАТУРА ЛЕК', 'Кравари', 'Аптека Натура Лек'],
            'only the town after the legal form' => ['', 'ПЗУ АПТЕКА НЕГОТИНО', 'Неготино', 'Аптека Неготино'],
            'only the town, pharmacy station' => ['ПЗУ АПТЕКАРСКА СТАНИЦА ТЕТОВО', 'ПЗУ АПТЕКАРСКА СТАНИЦА ТЕТОВО', 'Тетово', 'Аптекарска станица Тетово'],
            'generic word and number keep the town' => ['ПЗУ АПТЕКА 2 КОЧАНИ', 'ПЗУ АПТЕКА', 'Кочани', 'Аптека Кочани 2'],
            'town and number already in order' => ['ПЗУ АПТЕКА КОЧАНИ 2', 'ПЗУ АПТЕКА', 'Кочани', 'Аптека Кочани 2'],
            'pharmacy station and number keep the town' => ['ПЗУ АПТЕКАРСКА СТАНИЦА 3 ТЕТОВО', 'ПЗУ АПТЕКАРСКА СТАНИЦА', 'Тетово', 'Аптекарска станица Тетово 3'],
            'pharmacy station, number, no town' => ['ПЗУ АПТЕКАРСКА СТАНИЦА 3', 'ПЗУ АПТЕКАРСКА СТАНИЦА', null, 'Аптекарска станица 3'],
            'brand with town keeps its name' => ['ПЗУ АПТЕКА ВЕЛЕС ФАРМ', 'ПЗУ АПТЕКА ВЕЛЕС ФАРМ', 'Велес', 'Аптека Велес Фарм'],
            'empty branch' => ['', 'ПЗУ АПТЕКА БЛИНА', 'Кичево', 'Аптека Блина'],
        ];
    }

    #[DataProvider('names')]
    public function test_it_names_the_branch_as_people_know_it(string $branch, string $legal, ?string $town, string $expected): void
    {
        $this->assertSame($expected, PharmacyName::clean($branch, $legal, $town)->value);
    }

    public function test_ownership_and_address_keys(): void
    {
        $this->assertSame('private', PharmacyName::ownership('ПЗУ АПТЕКА БЛИНА'));
        $this->assertSame('public', PharmacyName::ownership('ЈЗУ АПТЕКА'));
        $this->assertSame(OnDutyLinker::addressKey('УЛ.МАРШАЛ ТИТО БР.16'), OnDutyLinker::addressKey('Маршал Тито 16'));
        $this->assertNotSame(OnDutyLinker::addressKey('Маршал Тито 16'), OnDutyLinker::addressKey('Маршал Тито 18'));
        $this->assertSame('', OnDutyLinker::addressKey(null));
    }
}
