<?php

namespace Tests\Unit\Import;

use App\Support\Import\Facilities\FacilityKeys;
use PHPUnit\Framework\TestCase;

/**
 * Comparison keys of institution names, addresses and phones as the ФЗОМ
 * register and the websites write them (forms seen in the 2026-10 data).
 */
class FacilityKeysTest extends TestCase
{
    public function test_legal_form_titles_and_town_are_not_part_of_the_name(): void
    {
        $variants = FacilityKeys::nameVariants('ЈЗУ Универзитетска клиника за гинекологија и акушерство – Скопје', ['СКОПЈЕ']);

        $this->assertSame(['ГИНЕКОЛОГИЈА', 'АКУШЕРСТВО'], $variants[0]['distinctive']);
        $this->assertSame(['УНИВЕРЗИТЕТСКА', 'КЛИНИКА'], $variants[0]['generic']);
    }

    public function test_latin_names_are_transliterated_and_english_clinic_words_translated(): void
    {
        $this->assertSame('САРАДЕНТ', FacilityKeys::nameVariants('Saradent')[0]['compact']);
        $this->assertSame(FacilityKeys::nameVariants('ПЗУ Сара Дент')[0]['compact'], FacilityKeys::nameVariants('Sara Dent')[0]['compact']);
        $this->assertSame(['ЗЕБЕКОВ'], FacilityKeys::nameVariants('Dental Center Zebekov')[0]['distinctive']);
    }

    public function test_quoted_bracketed_and_dashed_parts_are_variants(): void
    {
        $compacts = array_column(FacilityKeys::nameVariants('Балкан Дентал Поликлиника (IBU Dent)'), 'compact');
        $this->assertContains('ИБУДЕНТ', $compacts);

        $distinctive = array_column(FacilityKeys::nameVariants('Д-р Мојсоска - Специјалистички центар за дентална и естетска медицина'), 'distinctive');
        $this->assertContains(['МОЈСОСКА'], $distinctive);
    }

    public function test_words_written_two_ways(): void
    {
        $this->assertSame(2, FacilityKeys::sameWord('ИНФЕК.', 'ИНФЕКТИВНИ'));
        $this->assertSame(2, FacilityKeys::sameWord('ХИР.', 'ХИРУРГИЈА'));
        $this->assertSame(2, FacilityKeys::sameWord('ГИНЕКОЛОГИЈА', 'ГИНЕКОЛОШКА'));
        $this->assertSame(1, FacilityKeys::sameWord('ЛУКАНОСКИ', 'ЛУКАНОВСКИ'));
        // Another word, not another spelling.
        $this->assertSame(0, FacilityKeys::sameWord('ВИТА', 'ВИТАКСИЈА'));
        $this->assertSame(0, FacilityKeys::sameWord('КАРДИОЛОГИЈА', 'КАРДИОВАСКУЛАРНА'));
    }

    public function test_addresses_compare_street_and_house_number(): void
    {
        $register = FacilityKeys::address('Ул.Никола Русински БР.3-Б-ЛОК.БР.8');
        $website = FacilityKeys::address('Никола Русински 3б, ТЦ Лептокарија');

        $this->assertSame(['street' => ['НИКОЛА', 'РУСИНСКИ'], 'number' => '3'], $register);
        $this->assertSame('exact', FacilityKeys::compareAddresses($register, $website));
        $this->assertSame('street', FacilityKeys::compareAddresses(FacilityKeys::address('Бледски Договор Бб'), FacilityKeys::address('ул. Бледски договор бр. 10, 1000 Скопје')));
        // A number in the street's name is not the house number.
        $this->assertSame(['street' => ['11', 'ОКТОМВРИ'], 'number' => '53'], FacilityKeys::address('ул. 11 Октомври бр. 53, 1000 Скопје'));
        $this->assertNull(FacilityKeys::compareAddresses(FacilityKeys::address('ул. 11 Октомври 53'), FacilityKeys::address('ул. 8 Октомври 53')));
        // Glued, Latin, and numbered streets.
        $this->assertSame('exact', FacilityKeys::compareAddresses(FacilityKeys::address('ВОДЊАНСКА17'), FacilityKeys::address('ул. Водњанска бр. 17, 1000 Скопје')));
        $this->assertSame('exact', FacilityKeys::compareAddresses(FacilityKeys::address('Kiro Abrashev 32'), FacilityKeys::address('Ул.Киро Абрашев БР.32')));
        $this->assertSame('exact', FacilityKeys::compareAddresses(FacilityKeys::address('Ул. 117 бр. 4'), FacilityKeys::address('УЛ.117 бр 4-3/1 Тетово', ['ТЕТОВО'])));
        $this->assertSame('exact', FacilityKeys::compareAddresses(FacilityKeys::address('Мито Хаџивасилев Јасмин 10/1-1'), FacilityKeys::address('Ул.Мити Хаџи Василев Јасмин БР.10/1')));
    }

    public function test_phones_and_hosts(): void
    {
        $this->assertSame(['23113116', '75210842'], FacilityKeys::phones(['+389 2 3113 116', '075 210 842']));
        $this->assertSame('heartcenter.edu.mk', FacilityKeys::host('https://www.heartcenter.edu.mk/kontakt'));
        $this->assertSame('cardioohrid.mk', FacilityKeys::host('cardioohrid.mk'));
        $this->assertSame('СКОПЈЕ', FacilityKeys::town('Скопје - Центар'));
    }
}
