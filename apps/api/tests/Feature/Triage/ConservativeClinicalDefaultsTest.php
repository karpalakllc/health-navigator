<?php

namespace Tests\Feature\Triage;

use App\Services\Triage\V2\AnswerNormalizer;
use App\Services\Triage\V2\FlowWalker;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Integration decisions on the draft content („when unsure, escalate“,
 * owner-approved), pending clinician review — docs/triage-content.md
 * § Integration decisions.
 */
class ConservativeClinicalDefaultsTest extends TestCase
{
    /** @return array<string, mixed> */
    private function flow(string $key): array
    {
        return json_decode((string) file_get_contents(database_path("data/triage/flows/{$key}.json")), true);
    }

    /** @return array<string, mixed> */
    private function demo(int $months, string $sex = 'female', string $pregnancy = 'not_pregnant'): array
    {
        return [
            'age_band' => match (true) {
                $months < 3 => 'infant_0_3m',
                $months < 12 => 'infant_3_12m',
                $months < 60 => 'child_1_4',
                $months < 156 => 'child_5_12',
                $months < 216 => 'teen_13_17',
                $months < 780 => 'adult_18_64',
                default => 'older_65_plus',
            },
            'age_months' => $months,
            'age_years' => intdiv($months, 12),
            'sex' => $sex,
            'pregnancy' => $pregnancy,
            'conditions' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $demo
     * @param  array<string, list<string>>  $answers
     */
    private function level(string $flow, array $demo, array $answers): string
    {
        $definition = $this->flow($flow);
        $result = (new FlowWalker)->walk($definition, $demo, $answers);
        $this->assertTrue($result->finished(), "{$flow}: the walk did not reach an outcome");

        return $definition['outcomes'][$result->outcome]['level'];
    }

    /**
     * Walks a flow to its outcome, answering every question on the way from
     * $answers or, when not given, with the calmest choice („none“, „no“,
     * the first option, the number's lower bound + 1, or skipped).
     *
     * @param  array<string, mixed>  $demo
     * @param  array<string, list<string>>  $answers
     */
    private function autoLevel(string $flow, array $demo, array $answers): string
    {
        $definition = $this->flow($flow);
        $walker = new FlowWalker;

        for ($i = 0; $i < 30; $i++) {
            $result = $walker->walk($definition, $demo, $answers);
            if ($result->finished()) {
                return $definition['outcomes'][$result->outcome]['level'];
            }
            $node = $definition['nodes'][$result->current];
            $values = array_column($node['options'] ?? [], 'value');
            $answers[$result->current] = ($node['type'] ?? null) === 'info' ? [AnswerNormalizer::INFO_SEEN] : match ($node['kind']) {
                'multi' => in_array('none', $values, true) ? ['none'] : [],
                'single' => [$values[0]],
                'yes_no' => ['no'],
                default => ($node['optional'] ?? false) ? [] : [(string) ($node['min'] + 1)],
            };
        }

        $this->fail("{$flow}: no outcome");
    }

    /** @return array<string, array{string, array<string, list<string>>}> */
    public static function infantFeverInOtherFlows(): array
    {
        return [
            'crying baby, 38.5 °C' => ['crying-baby', ['q_temp' => ['38.5']]],
            'ear pain with fever' => ['ear-pain-child', ['q_signs' => ['fever']]],
            'rash with fever' => ['rash-with-fever-child', ['q_fever' => ['yes']]],
            'vomiting with fever' => ['vomiting-diarrhoea-child', ['q_signs' => ['fever']]],
            'breathing with fever' => ['child-breathing', ['q_pattern' => ['fever']]],
        ];
    }

    /** @param  array<string, list<string>>  $answers */
    #[DataProvider('infantFeverInOtherFlows')]
    public function test_fever_in_a_young_infant_is_an_emergency_in_every_child_flow(string $flow, array $answers): void
    {
        $this->assertSame('emergency_now', $this->autoLevel($flow, $this->demo(1, 'male'), $answers));
        // The same answers for an older baby are not an emergency.
        $this->assertNotSame('emergency_now', $this->autoLevel($flow, $this->demo(8, 'male'), $answers));
    }

    /** @return array<string, array{array<string, list<string>>}> */
    public static function infantFeverAnswers(): array
    {
        return [
            '38 °C, first day' => [['q_temp' => ['38'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']]],
            '39.5 °C, otherwise well' => [['q_temp' => ['39.5'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']]],
            'not measured (feels hot)' => [['q_temp' => [], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']]],
        ];
    }

    /** @param  array<string, list<string>>  $answers */
    #[DataProvider('infantFeverAnswers')]
    public function test_fever_in_an_infant_under_three_months_is_an_emergency(array $answers): void
    {
        $this->assertSame('emergency_now', $this->level('fever-infant-child', $this->demo(1, 'male'), $answers));
    }

    public function test_a_measured_normal_temperature_in_a_young_infant_stays_urgent_same_day(): void
    {
        $answers = ['q_temp' => ['37.2'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']];

        $this->assertSame('urgent_same_day', $this->level('fever-infant-child', $this->demo(1, 'male'), $answers));
    }

    public function test_fever_in_an_older_infant_is_unchanged(): void
    {
        $answers = ['q_temp' => ['38.2'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']];

        $this->assertSame('self_care_with_safety_net', $this->level('fever-infant-child', $this->demo(14, 'male'), $answers));
    }

    public function test_reduced_fetal_movements_are_an_emergency(): void
    {
        foreach (['mid', 'term'] as $stage) {
            $answers = ['q_stage' => [$stage], 'q_preg_signs' => ['movements']];
            $this->assertSame('emergency_now', $this->level('pregnancy-concerns', $this->demo(360, 'female', 'pregnant'), $answers), $stage);
        }

        // Together with another sign it is still the emergency.
        $answers = ['q_stage' => ['term'], 'q_preg_signs' => ['contractions', 'movements']];
        $this->assertSame('emergency_now', $this->level('pregnancy-concerns', $this->demo(360, 'female', 'pregnant'), $answers));
    }

    public function test_other_pregnancy_signs_keep_their_level(): void
    {
        $answers = ['q_stage' => ['mid'], 'q_preg_signs' => ['waters']];

        $this->assertSame('urgent_same_day', $this->level('pregnancy-concerns', $this->demo(360, 'female', 'pregnant'), $answers));
    }

    public function test_post_menopausal_bleeding_needs_a_doctor_within_two_days(): void
    {
        $answers = ['q_situation' => ['other'], 'q_postmeno' => ['yes'], 'q_signs' => ['none']];

        $this->assertSame('see_doctor_24_48h', $this->level('vaginal-bleeding', $this->demo(58 * 12), $answers));
        $this->assertSame('see_doctor_24_48h', $this->level('vaginal-bleeding', $this->demo(70 * 12), ['q_situation' => ['other'], 'q_postmeno' => ['no'], 'q_signs' => ['none']]));
    }

    public function test_a_new_breast_lump_needs_a_doctor_within_two_days(): void
    {
        foreach (['lump', 'skin', 'nipple'] as $change) {
            $answers = ['q_changes' => [$change], 'q_cycle_pain' => ['no'], 'q_breastfeeding' => ['no'], 'q_fever' => ['no']];
            $this->assertSame('see_doctor_24_48h', $this->level('breast-lump', $this->demo(45 * 12), $answers), $change);
        }

        // Cyclical pain alone stays self-care.
        $answers = ['q_changes' => ['none'], 'q_cycle_pain' => ['yes'], 'q_breastfeeding' => ['no'], 'q_fever' => ['no']];
        $this->assertSame('self_care_with_safety_net', $this->level('breast-lump', $this->demo(30 * 12), $answers));
    }
}
