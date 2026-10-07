<?php

namespace Tests\Feature\UrgentCare;

use App\Support\UrgentCare\UrgentCareClassifier as C;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Which source wordings count as urgent-care evidence, and how strongly
 * (docs/urgent-care.md § Data). The examples are real ФЗОМ work units and
 * website departments / hours.
 */
class UrgentCareClassifierTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: list<string>, 4: list<string>}>
     */
    public static function wordings(): array
    {
        return [
            'emergency centre' => ['ЈЗУ Клиника', 'work_unit', 'Ургентен центар — Оддел (раководител)', ['ed'], []],
            'surgical emergency centre' => ['ЈЗУ Клиника', 'department', 'Ургентен хируршки центар', ['ed'], []],
            'adult emergency centre' => ['ПЗУ Болница', 'work_unit', 'Адултен ургентен центар', ['ed'], []],
            'emergency states (unit name)' => ['ЈЗУ Општа болница Куманово', 'work_unit', 'Ургентни состојби', ['ed'], []],
            'emergency medicine' => ['ЈЗУ Клиничка болница Битола', 'department', 'Ургентна медицина', ['ed'], []],
            'ems' => ['Здравствен дом Ресен', 'work_unit', 'Служба за итна медицинска помош и домашно лекување', ['ems'], []],
            'ems short' => ['Здравствен дом Струга', 'work_unit', 'Итна Помош', ['ems'], []],
            'dental emergency' => ['ЈЗУ Здравствен дом Тетово', 'department', 'Итна стоматолошка помош', ['dental'], []],
            'dental inside a longer unit' => ['ЈЗУ ЗД Битола', 'department', 'Служба за стоматолошка здравствена заштита на деца до 14 год., итна стоматолошка помош и рендген за заби', ['dental'], []],
            'public dental centre on duty' => ['ЈЗУ Универзитетски стоматолошки клинички центар', 'department', 'Дежурна служба', ['dental'], []],
            'private dental practice' => ['Стоматолошка ординација Бато Дент', 'department', 'Итни случаи', [], ['dental']],
            'psychiatric ward' => ['ЈЗУ Психијатриска Болница Скопје', 'department', 'Машки оддел за ургентна психијатрија', [], ['ed']],
            'infectious ward' => ['ЈЗУ Клиника за инфективни болести', 'work_unit', 'Оддел за ургентна инфектологија и интензивна нега', [], ['ed']],
            'acute psychiatry (not anchored)' => ['Психијатриска Болница Демир Хисар', 'department', 'Оддел за акутни ургентни состојби во психијатрија', [], ['ed']],
            'emergency gynaecology' => ['ЈЗУ Специјална болница', 'work_unit', 'Итна Гинекологија', [], ['ed']],
            'cardiology is nothing' => ['ЈЗУ Клиника', 'department', 'Кардиологија', [], []],
            '24h holter is nothing' => ['ПЗУ Кардиомедика', 'department', '24ч Холтер ритам', [], []],
        ];
    }

    /**
     * @param  list<string>  $strong
     * @param  list<string>  $candidates
     */
    #[DataProvider('wordings')]
    public function test_wordings(string $name, string $kind, string $text, array $strong, array $candidates): void
    {
        $items = C::classify($name, [['source' => 'fzom', 'kind' => $kind, 'text' => $text]]);

        $this->assertEqualsCanonicalizing($strong, C::strongFlags($items));
        $this->assertEqualsCanonicalizing(
            $candidates,
            array_values(array_unique(array_column(array_filter($items, fn (array $i): bool => $i['strength'] === C::CANDIDATE && $i['kind'] !== 'name'), 'flag'))),
        );
    }

    public function test_round_the_clock_counts_only_when_said_of_the_urgent_service(): void
    {
        $hours = fn (string $text): array => C::strongFlags(C::classify('ЈЗУ Болница', [['source' => 'website', 'kind' => 'hours', 'text' => $text]]));

        $this->assertEqualsCanonicalizing(['ed', 'open24'], $hours('Амбуланти на Трауматологија и Ортопедија 08:00–15:00; Ургентен центар 24/7; оддели со дежурства 24/7'));
        $this->assertEqualsCanonicalizing(['ems', 'open24'], $hours('Итна медицинска помош 24/7; администрација пон–пет 07:00–15:00'));
        $this->assertEqualsCanonicalizing(['ed', 'open24'], $hours('Администрација 07–15 ч.; ургентна служба 00–24 ч.'));
        $this->assertEqualsCanonicalizing(['ems', 'dental', 'open24'], $hours('Итна медицинска помош: 24 часа; итна стоматолошка помош: 24 часа'));
        // Said of the whole place, or of nothing in particular: only listed for staff.
        $this->assertSame([], $hours('Секој ден 24/7'));
        $this->assertSame([], $hours('Болницата работи 24 часа; амбулантни прегледи 07:30–15:00 работни денови (со упат)'));
        $this->assertSame([], $hours('Пон–Пет 07:30–15:30; 24ч дежурна служба'));
        $this->assertSame([], $hours('Пон–Пет 08:00–16:00'));
    }

    public function test_a_general_hospital_without_a_named_unit_is_only_a_candidate(): void
    {
        $items = C::classify('ЈЗУ Општа болница Струмица', []);

        $this->assertSame([], C::strongFlags($items));
        $this->assertSame([['flag' => 'ed', 'strength' => 'candidate', 'source' => 'directory', 'kind' => 'name', 'text' => 'ЈЗУ Општа болница Струмица']], $items);

        $named = C::classify('ЈЗУ Општа болница Куманово', [['source' => 'fzom', 'kind' => 'work_unit', 'text' => 'Ургентни состојби']]);
        $this->assertSame(['ed'], C::strongFlags($named));
        $this->assertCount(1, $named);
    }
}
