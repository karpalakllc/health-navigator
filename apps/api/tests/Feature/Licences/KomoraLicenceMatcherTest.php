<?php

namespace Tests\Feature\Licences;

use App\Support\Import\Contracts\LicenceReviewReason;
use App\Support\Licences\KomoraLicenceMatcher;
use App\Support\Licences\LicenceDecision;
use App\Support\Licences\LicenceSpecialtyMap;
use App\Support\Licences\ParsedLicenceRow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLicenceCandidateSource;
use Tests\TestCase;

/**
 * Which profile a Комора licence row belongs to, with the shipped specialty
 * mapping. Invented names throughout.
 */
class KomoraLicenceMatcherTest extends TestCase
{
    use RefreshDatabase;

    private static int $number = 100;

    private function row(string $name, ?string $specialty, string $validUntil = '2031-01-01', ?string $number = null): ParsedLicenceRow
    {
        return new ParsedLicenceRow($name, $specialty, CarbonImmutable::parse($validUntil), $number ?? sprintf('%07d', ++self::$number), 'А-В#p1');
    }

    /**
     * @param  list<ParsedLicenceRow>  $rows
     * @return list<array{0: int|null, 1: string|null, 2: list<int>}>
     */
    private function decide(FakeLicenceCandidateSource $source, array $rows): array
    {
        return array_map(
            fn (LicenceDecision $decision): array => [$decision->doctorId, $decision->reason?->value, $decision->candidateDoctorIds],
            (new KomoraLicenceMatcher($source, new LicenceSpecialtyMap))->decide($rows),
        );
    }

    public function test_a_single_namesake_with_a_fitting_specialty_gets_the_licence(): void
    {
        $source = (new FakeLicenceCandidateSource)
            ->add(1, 'Ана Тестовска', ['ПЕДИЈАТАР'])
            ->add(2, 'Бранко Тестовски', ['ПЕДИЈАТАР']);

        $this->assertSame([[1, null, [1]]], $this->decide($source, [$this->row('АНА ТЕСТОВСКА', 'педијатрија')]));
    }

    public function test_spelling_differences_between_the_sources_do_not_matter(): void
    {
        // ФЗОМ types a Latin "A" into „ОПШТA“; the Комора writes a hyphenated surname.
        $source = (new FakeLicenceCandidateSource)
            ->add(1, 'Вера Примеровска Огледовска', ['ОПШТA МЕДИЦИНА']);

        $this->assertSame(
            [[1, null, [1]]],
            $this->decide($source, [$this->row('ВЕРА ПРИМЕРОВСКА-ОГЛЕДОВСКА', 'доктор на медицина во ПЗЗ')]),
        );
    }

    public function test_a_compatible_contracted_post_fits_and_a_different_one_does_not(): void
    {
        // A cardiologist contracted as an internist fits; as a surgeon he does not.
        $source = (new FakeLicenceCandidateSource)
            ->add(1, 'Иван Срцевски', ['ИНТЕРНА МЕДИЦИНА'])
            ->add(2, 'Петар Ножевски', ['ОПШТА ХИРУРГИЈА']);

        $this->assertSame([
            [1, null, [1]],
            [null, 'specialty_mismatch', [2]],
        ], $this->decide($source, [
            $this->row('ИВАН СРЦЕВСКИ', 'кардиологија'),
            $this->row('ПЕТАР НОЖЕВСКИ', 'кардиологија'),
        ]));
    }

    public function test_an_internist_contracted_under_an_internal_subspecialty_fits(): void
    {
        $source = (new FakeLicenceCandidateSource)->add(1, 'Вида Крвовска', ['ХЕМАТОЛОГИЈА']);

        $this->assertSame([[1, null, [1]]], $this->decide($source, [$this->row('ВИДА КРВОВСКА', 'интерна медицина')]));
    }

    public function test_several_fitting_namesakes_are_ambiguous_and_nobody_gets_the_licence(): void
    {
        $source = (new FakeLicenceCandidateSource)
            ->add(1, 'Горан Истоименовски', ['ОПШТА МЕДИЦИНА'])
            ->add(2, 'Горан Истоименовски', ['СЕМЕЈНА МЕДИЦИНА'])
            ->add(3, 'Горан Истоименовски', ['ОФТАЛМОЛОГИЈА']);

        $this->assertSame(
            [[null, 'ambiguous', [1, 2]]],
            $this->decide($source, [$this->row('ГОРАН ИСТОИМЕНОВСКИ', 'семејна медицина')]),
        );
    }

    public function test_namesakes_on_the_list_never_share_one_profile(): void
    {
        // Two licences, same name, both general practice; one ФЗОМ profile.
        $source = (new FakeLicenceCandidateSource)->add(7, 'Марија Двојновска', ['ОПШТА МЕДИЦИНА']);

        $this->assertSame([
            [null, 'ambiguous', [7]],
            [null, 'ambiguous', [7]],
        ], $this->decide($source, [
            $this->row('МАРИЈА ДВОЈНОВСКА', 'доктор на медицина во ПЗЗ'),
            $this->row('МАРИЈА ДВОЈНОВСКА', 'општа медицина'),
        ]));
    }

    public function test_a_namesake_on_the_list_with_another_specialty_does_not_block_the_fitting_one(): void
    {
        $source = (new FakeLicenceCandidateSource)->add(7, 'Марија Двојновска', ['ОПШТА МЕДИЦИНА']);

        $this->assertSame([
            [7, null, [7]],
            [null, 'specialty_mismatch', [7]],
        ], $this->decide($source, [
            $this->row('МАРИЈА ДВОЈНОВСКА', 'доктор на медицина во ПЗЗ'),
            $this->row('МАРИЈА ДВОЈНОВСКА', 'неврохирургија'),
        ]));
    }

    public function test_nobody_with_the_name_is_no_match_and_an_unmapped_specialty_is_for_review(): void
    {
        $source = (new FakeLicenceCandidateSource)->add(1, 'Јана Новоспецовска', ['ОПШТА МЕДИЦИНА']);

        $this->assertSame([
            [null, 'no_match', []],
            [null, 'specialty_mismatch', [1]],
            [null, 'specialty_mismatch', [1]],
        ], $this->decide($source, [
            $this->row('НЕПОЗНАТ НЕПОЗНАТОВСКИ', 'педијатрија'),
            $this->row('ЈАНА НОВОСПЕЦОВСКА', 'сосема нова специјалност'),
            $this->row('ЈАНА НОВОСПЕЦОВСКА', null),
        ]));
    }

    public function test_a_licence_already_on_a_profile_stays_there_and_profiles_with_another_licence_are_not_candidates(): void
    {
        $source = (new FakeLicenceCandidateSource)
            ->add(1, 'Лена Имановска', ['ОПШТА МЕДИЦИНА'], licenceNumber: '0000500')
            ->add(2, 'Лена Имановска', ['ОПШТА МЕДИЦИНА']);

        $this->assertSame([
            [1, null, [1]],
            [2, null, [2]],
        ], $this->decide($source, [
            // The holder keeps hers even though the specialty changed.
            $this->row('ЛЕНА ИМАНОВСКА', 'офталмологија', number: '0000500'),
            // The namesake's licence goes to the profile without one.
            $this->row('ЛЕНА ИМАНОВСКА', 'доктор на медицина во ПЗЗ', number: '0000501'),
        ]));
    }

    public function test_expiry_plays_no_part_in_the_decision(): void
    {
        $source = (new FakeLicenceCandidateSource)->add(1, 'Стара Истековска', ['ПСИХИЈАТРИЈА']);

        $this->assertSame([[1, null, [1]]], $this->decide($source, [$this->row('СТАРА ИСТЕКОВСКА', 'психијатрија', '2020-01-01')]));
    }

    public function test_dentist_and_other_non_physician_wording_never_fits(): void
    {
        $source = (new FakeLicenceCandidateSource)->add(1, 'Зоран Забовски', ['ОПШТA СТОМАТОЛОГИЈА']);

        $this->assertSame(
            [[null, LicenceReviewReason::SpecialtyMismatch->value, [1]]],
            $this->decide($source, [$this->row('ЗОРАН ЗАБОВСКИ', 'доктор на медицина во ПЗЗ')]),
        );
    }
}
