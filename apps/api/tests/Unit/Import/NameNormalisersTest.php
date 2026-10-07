<?php

namespace Tests\Unit\Import;

use App\Support\Import\NameKey;
use App\Support\Import\Names\DoctorTitle;
use App\Support\Import\Names\FacilityName;
use App\Support\Import\Names\PersonName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The name rules every import and import:clean-names apply. Synthetic names
 * only.
 */
class NameNormalisersTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function personFixes(): array
    {
        return [
            'capitals' => ['ПРВАНА ПРИМЕРОВСКА', 'Првана Примеровска', ['casing']],
            'lower case' => ['првана примеровска', 'Првана Примеровска', ['casing']],
            'hyphenated, capitals' => ['ПРВАНА ПРИМЕРОВСКА-ТЕСТОВА', 'Првана Примеровска-Тестова', ['casing']],
            'spaced hyphen' => ['Првана Примеровска - Тестова', 'Првана Примеровска-Тестова', ['hyphen']],
            'en dash' => ['Првана Примеровска – Тестова', 'Првана Примеровска-Тестова', ['hyphen']],
            'half-spaced hyphen' => ['Првана Примеровска- Тестова', 'Првана Примеровска-Тестова', ['hyphen']],
            'latin a in a cyrillic word' => ["Првана Пример\u{0061}вска", 'Првана Примеравска', ['homoglyph']],
            'latin capital K' => ['Kрстана Примеровска', 'Крстана Примеровска', ['homoglyph']],
            'glued initial' => ['Првана К.Примеровска', 'Првана К. Примеровска', ['initial']],
            'initial without dot' => ['Првана К Примеровска', 'Првана К. Примеровска', ['initial']],
            'stray dot after the given name' => ['Првана. Примеровска', 'Првана Примеровска', ['initial']],
            'dot before a word' => ['Првана .Примеровска', 'Првана Примеровска', ['initial']],
            'double spaces' => ['  Првана   Примеровска ', 'Првана Примеровска', ['spacing']],
            'quotes' => ['„Првана“ Примеровска', 'Првана Примеровска', ['quotes']],
            'trailing role' => ['Првана Примеровска стоматолог', 'Првана Примеровска', ['role_in_name']],
            'mixed case kept' => ['Првана МекПримеровска', 'Првана МекПримеровска', []],
        ];
    }

    /**
     * @param  list<string>  $changes
     */
    #[DataProvider('personFixes')]
    public function test_person_names_are_fixed_with_confidence(string $raw, string $expected, array $changes): void
    {
        $cleaned = PersonName::clean($raw);

        $this->assertSame($expected, $cleaned->value);
        $this->assertSame($changes, $cleaned->changes);
        $this->assertNull($cleaned->uncertain);
    }

    public function test_titles_move_out_of_the_name(): void
    {
        $leading = PersonName::clean('Проф. д-р Првана Примеровска');
        $this->assertSame('Првана Примеровска', $leading->value);
        $this->assertSame('проф. д-р', $leading->title);

        $bare = PersonName::clean('Др Првана Примеровска');
        $this->assertSame('Првана Примеровска', $bare->value);
        $this->assertSame('д-р', $bare->title);

        $latin = PersonName::clean('dr. Prvana Primerovska');
        $this->assertSame('Prvana Primerovska', $latin->value);
        $this->assertSame('д-р', $latin->title);

        $comma = PersonName::clean('Првана Примеровска, специјалист по педијатрија');
        $this->assertSame('Првана Примеровска', $comma->value);
        $this->assertNull($comma->title);
        $this->assertContains('role_in_name', $comma->changes);

        // Two words are a name, never a title and a surname.
        $this->assertSame('Др Примеровски', PersonName::clean('Др Примеровски')->value);
    }

    public function test_what_needs_a_person_is_uncertain_with_a_proposal(): void
    {
        $latin = PersonName::clean('Prvana Primerovska');
        $this->assertSame('Prvana Primerovska', $latin->value);
        $this->assertSame('latin_script', $latin->uncertain);
        $this->assertSame('Првана Примеровска', $latin->suggestion);

        $this->assertSame('Ѓоко Шикаровски', PersonName::clean('Gjoko Shikarovski')->suggestion);
        $this->assertSame('Јана Примеровиќ', PersonName::clean('Jana Primerović')->suggestion);

        $institution = PersonName::clean('ПЗУ Ординација Првана Примеровска');
        $this->assertSame('institution_in_name', $institution->uncertain);
        $this->assertSame('Првана Примеровска', $institution->suggestion);

        $mixed = PersonName::clean("Првана Пример\u{0071}вска");
        $this->assertSame('mixed_script', $mixed->uncertain);

        $comma = PersonName::clean('Првана Примеровска, Тестово');
        $this->assertSame('text_after_comma', $comma->uncertain);
        $this->assertSame('Првана Примеровска', $comma->suggestion);
    }

    /**
     * Latin letters whose Cyrillic look-alike is a different Macedonian
     * letter (s/ѕ, j/ј) are never folded: a person decides, and the value
     * stays as it was.
     *
     * @return array<string, array{0: string}>
     */
    public static function notFolded(): array
    {
        return [
            'latin s' => ["Првана Петров\u{0073}ка"],
            'latin S' => ["\u{0053}тојна Примеровска"],
            'latin j' => ["Првана Примеров\u{006A}ска"],
            'latin J' => ["\u{004A}ана Примеровска"],
            'latin Y' => ["Првана \u{0059}росимовска"],
        ];
    }

    #[DataProvider('notFolded')]
    public function test_latin_letters_with_a_distinct_macedonian_twin_are_left_for_a_person(string $raw): void
    {
        $cleaned = PersonName::clean($raw);

        $this->assertSame($raw, $cleaned->value);
        $this->assertSame('mixed_script', $cleaned->uncertain);
        $this->assertSame([], $cleaned->changes);

        $facility = FacilityName::clean('ПЗУ ОРД. '.$raw, null);
        $this->assertSame('mixed_script', $facility->uncertain);
        $this->assertSame('ПЗУ ОРД. '.$raw, $facility->value);
        $this->assertStringNotContainsString('ѕ', $facility->value);
        $this->assertStringNotContainsString('Ѕ', $facility->value);
    }

    /**
     * Albanian and Serbo-Croatian names in Latin script: the proposal is all
     * Cyrillic, or there is none.
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function latinNames(): array
    {
        return [
            'xh' => ['Xhevdet Rexhepi', 'Џевдет Реџепи'],
            'dj (Serbo-Croatian đ)' => ['Marko Djordjevic', 'Марко Ѓорѓевиќ'],
            'xh inside a word' => ['Arben Hoxha', 'Арбен Хоџа'],
            'q and dh' => ['Qazim Dhimitri', 'Ќазим Димитри'],
            'll and y before a consonant' => ['Yllka Llapi', 'Илка Лапи'],
            'y before a vowel, gj' => ['Yusuf Gjyla', 'Јусуф Ѓила'],
            'sh, th, ç' => ['Shpend Thaçi', 'Шпенд Тачи'],
            'zh, ë, rr, nj' => ['Zhaneta Bërrnja', 'Жанета Берња'],
            'no certain counterpart: no proposal' => ['Maxim Wolf', null],
        ];
    }

    #[DataProvider('latinNames')]
    public function test_latin_names_get_an_all_cyrillic_proposal_or_none(string $raw, ?string $expected): void
    {
        $cleaned = PersonName::clean($raw);

        $this->assertSame($raw, $cleaned->value);
        $this->assertSame('latin_script', $cleaned->uncertain);
        $this->assertSame($expected, $cleaned->suggestion);

        if ($cleaned->suggestion !== null) {
            $this->assertDoesNotMatchRegularExpression('/\p{Latin}/u', $cleaned->suggestion);
        }
    }

    /**
     * An uncertain name keeps its value exactly; a role after a spaced dash
     * and Latin academic abbreviations are understood.
     */
    public function test_uncertain_names_keep_their_value_and_roles_after_a_dash_separate(): void
    {
        $dash = PersonName::clean('Д-р Ана Петрова - специјалист по педијатрија');
        $this->assertSame('Ана Петрова', $dash->value);
        $this->assertNull($dash->uncertain);
        $this->assertSame('д-р', $dash->title);

        $latinTitles = PersonName::clean('Mr. sc. д-р Арбен Џафери');
        $this->assertSame('Арбен Џафери', $latinTitles->value);
        $this->assertSame('м-р сци. д-р', $latinTitles->title);
        $this->assertSame('м-р сци.', DoctorTitle::clean('Mr. sc.')?->value);

        // A double surname is not a role.
        $this->assertSame('Ана Петрова-Ристова', PersonName::clean('Ана Петрова - Ристова')->value);

        foreach ([
            'ДР АНА ПЕТРОВА СТОМАТОЛОГ ПЗУ ДЕНТ' => 'Ана Петрова',
            'Ана Петрова - Ристова, Тестово' => 'Ана Петрова-Ристова',
            'специјалист по педијатрија д-р Ана Петрова Ристова' => 'Ана Петрова Ристова',
            'Ана по Петрова д-р Ристова' => null,
        ] as $raw => $suggestion) {
            $cleaned = PersonName::clean($raw);
            $this->assertSame($raw, $cleaned->value, $raw);
            $this->assertNotNull($cleaned->uncertain, $raw);
            $this->assertNull($cleaned->title, $raw);
            $this->assertSame([], $cleaned->changes, $raw);
            $this->assertSame($suggestion, $cleaned->suggestion, $raw);
        }
    }

    /**
     * Cosmetic fixes never change the matching keys (Комора, ФЗОМ and
     * website matching give the same result before and after).
     */
    public function test_cosmetic_fixes_keep_the_matching_keys(): void
    {
        foreach ([
            'ПРВАНА ПРИМЕРОВСКА', 'Првана Примеровска - Тестова', "Првана Пример\u{0061}вска", 'Kрстана Примеровска',
            'Првана К Примеровска', 'Првана. Примеровска', '„Првана“ Примеровска', '  Првана   Примеровска ', 'Проф. д-р Првана Примеровска',
        ] as $raw) {
            $clean = PersonName::clean($raw)->value;
            $this->assertSame(NameKey::for($raw), NameKey::for($clean), $raw);
            $this->assertSame(NameKey::sorted($raw), NameKey::sorted($clean), $raw);
        }

        // Cleaning is stable: a second pass changes nothing.
        foreach (array_column(self::personFixes(), 1) as $clean) {
            $this->assertSame($clean, PersonName::clean($clean)->value);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function titles(): array
    {
        return [
            ['Д-р', 'д-р'],
            ['Др.', 'д-р'],
            ['dr', 'д-р'],
            ['prof. dr', 'проф. д-р'],
            ['Проф д-р др. сци', 'проф. д-р д-р сци.'],
            ['проф.д-р', 'проф. д-р'],
            ['Асистент д-р', 'асс. д-р'],
            ['ас. др. мр. сци.', 'асс. д-р м-р сци.'],
            ['в.н.сор. д-р', 'виш науч. сор. д-р'],
            ['д-р мед. науки', 'д-р сци.'],
            ['д-р, специјализант', 'д-р (специјализант)'],
            ['проф. д-р, раководител на оддел', 'проф. д-р'],
            ['асс. спец. д-р, специјалист невролог', 'асс. спец. д-р'],
            ['проф. д-р FESC', 'проф. д-р FESC'],
        ];
    }

    #[DataProvider('titles')]
    public function test_titles_are_canonical(string $raw, string $expected): void
    {
        $title = DoctorTitle::clean($raw);

        $this->assertNotNull($title);
        $this->assertSame($expected, $title->value);
        $this->assertNull($title->uncertain);
    }

    public function test_an_unknown_title_is_kept_and_uncertain(): void
    {
        $title = DoctorTitle::clean('капетан  д-р');

        $this->assertNotNull($title);
        $this->assertSame('капетан д-р', $title->value);
        $this->assertSame('title_unrecognised', $title->uncertain);
        $this->assertNull(DoctorTitle::clean('  '));
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string}>
     */
    public static function facilityNames(): array
    {
        return [
            'register capitals, abbreviation, town' => ['ПЗУ-ОРД.ПО ОПШТА МЕДИЦИНА ТЕСТ МЕДИКА СКОПЈЕ', 'Скопје - Центар', 'ПЗУ Ординација по општа медицина Тест Медика'],
            'title in a practice name' => ['ПЗУ Д-Р ПРВАНА ПРИМЕРОВСКА ТЕСТОВО', 'Тестово', 'ПЗУ д-р Првана Примеровска'],
            'bare Др' => ['ПЗУ Др Примеровски', 'Тестово', 'ПЗУ д-р Примеровски'],
            'legal form spelled out' => ['Приватна Здравствена Установа - Ординација По Педијатрија Тестко', 'Тестово', 'ПЗУ Ординација по педијатрија Тестко'],
            'quotes' => ['ПЗУ Ординација По Гинекологија ’’Тест Бела’’ Тестово', 'Тестово', 'ПЗУ Ординација по гинекологија „Тест Бела“'],
            'straight quotes' => ['ПЗУ Орд.По Општа Медицина "Д-Р Првана Примеровска" Тестово', 'Тестово', 'ПЗУ Ординација по општа медицина „Д-р Првана Примеровска“'],
            'village marker' => ['ПЗУ Тест-Дент С.Тестово Тестовци', 'Тестово', 'ПЗУ Тест-Дент'],
            'public: the town is the name' => ['ЈЗУ ЗДРАВСТВЕН ДОМ ТЕСТОВО', 'Тестово', 'ЈЗУ Здравствен дом Тестово'],
            'hospital keeps its town' => ['ЈЗУ Општа Болница Со Проширена Дејност Тестово', 'Тестово', 'ЈЗУ Општа болница со проширена дејност Тестово'],
            'a repeated generic word starts the brand' => ['ПЗУ Спец.Орд. По Педијатрија Педијатрија Тест', 'Скопје', 'ПЗУ Специјалистичка ординација по педијатрија Педијатрија Тест'],
            'a brand keeps its capitals' => ['ПЗУ Нова Медицина', 'Тестово', 'ПЗУ Нова Медицина'],
            'only the town would remain' => ['ПЗУ Тестово', 'Тестово', 'ПЗУ Тестово'],
            'universities written out' => ['ЈЗУ Универзи.Кл. за Тест Болести', 'Скопје', 'ЈЗУ Универзитетска клиника за Тест Болести'],
            'a quoted name starting with a function word' => ['ПЗУ „ДО ДЕНТ“', 'Скопје', 'ПЗУ „До Дент“'],
            'a pharmacy starting with ВО' => ['АПТЕКА „ВО ЗДРАВЈЕ“ СКОПЈЕ', 'Скопје', 'Аптека „Во Здравје“'],
            'already clean' => ['ЈЗУ Здравствен дом „Д-р Прван Примеровски“ – Тестово', 'Тестово', 'ЈЗУ Здравствен дом „Д-р Прван Примеровски“ – Тестово'],
        ];
    }

    #[DataProvider('facilityNames')]
    public function test_facility_names_read_well(string $raw, ?string $town, string $expected): void
    {
        $cleaned = FacilityName::clean($raw, $town);

        $this->assertSame($expected, $cleaned->value);
        // Stable: cleaning the result changes nothing.
        $this->assertSame($expected, FacilityName::clean($expected, $town)->value);
    }

    public function test_the_register_key_of_a_cleaned_name_matches_the_profile(): void
    {
        $raw = 'ПЗУ ТЕСТ ДЕНТ СКОПЈЕ';
        $shown = FacilityName::clean($raw, 'Скопје - Карпош')->value;

        $this->assertSame('ПЗУ Тест Дент', $shown);
        $this->assertNotSame(NameKey::sorted($raw), NameKey::sorted($shown));
        $this->assertSame(FacilityName::key($raw, 'Скопје - Карпош'), NameKey::sorted($shown));
    }
}
