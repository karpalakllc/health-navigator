<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Safety fixes of 2026-10-23 in the babies' and children's flows
 * (CHANGES-2026-10-23 in the clinical review pack), pending clinician review.
 */
class ChildrenFlowsTest extends TestCase
{
    use WalksFlows;

    private function boy(int $months): array
    {
        return $this->demo($months, 'male');
    }

    /** @param  list<string>  $conditions */
    private function child(int $months, array $conditions): array
    {
        return $this->demo($months, 'male', 'not_pregnant', $conditions);
    }

    // ----------------------------------------------------------- crying-baby

    public function test_crying_baby_screens_for_intussusception_and_blood_in_the_stool(): void
    {
        foreach (['intussusception', 'blood_stool'] as $code) {
            $this->assertSame('o_emergency', $this->redFlag('crying-baby', $code)['outcome'], $code);
        }

        $answers = ['q_temp' => [], 'q_signs' => ['none'], 'q_pattern' => ['episodic'], 'q_carer_ok' => ['yes']];
        $this->assertSame('emergency_department_now', $this->levelOf('crying-baby', $this->boy(8), $answers, auto: false));
    }

    public function test_crying_baby_colic_self_care_only_under_four_months(): void
    {
        $answers = ['q_temp' => [], 'q_signs' => ['none'], 'q_pattern' => ['colic'], 'q_carer_ok' => ['yes']];

        $this->assertSame('self_care_with_safety_net', $this->levelOf('crying-baby', $this->boy(2), $answers, auto: false));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('crying-baby', $this->boy(7), $answers, auto: false));
    }

    public function test_crying_baby_jaundice_with_sleepiness_or_in_a_newborn_is_same_day(): void
    {
        $base = ['q_temp' => [], 'q_pattern' => ['other'], 'q_carer_ok' => ['yes']];

        $this->assertSame('o_urgent_jaundice', $this->outcomeOf('crying-baby', $this->boy(2), $base + ['q_signs' => ['yellow', 'sleepy']]));
        $this->assertSame('o_urgent_jaundice', $this->outcomeOf('crying-baby', $this->boy(0), $base + ['q_signs' => ['yellow']]));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('crying-baby', $this->boy(2), $base + ['q_signs' => ['yellow']], auto: false));
    }

    public function test_crying_baby_groin_swelling_goes_to_the_emergency_department_now(): void
    {
        $answers = ['q_temp' => [], 'q_signs' => ['hernia'], 'q_pattern' => ['other'], 'q_carer_ok' => ['yes']];

        $this->assertSame('emergency_department_now', $this->levelOf('crying-baby', $this->boy(5), $answers, auto: false));
    }

    public function test_crying_baby_low_temperature_and_unmeasured_warmth(): void
    {
        $base = ['q_pattern' => ['other'], 'q_carer_ok' => ['yes']];

        $this->assertSame('emergency_now', $this->levelOf('crying-baby', $this->boy(1), $base + ['q_temp' => ['35.5'], 'q_signs' => ['none']], auto: false));
        $this->assertSame('emergency_department_now', $this->levelOf('crying-baby', $this->boy(6), $base + ['q_temp' => ['35.5'], 'q_signs' => ['none']], auto: false));
        $this->assertSame('emergency_now', $this->levelOf('crying-baby', $this->boy(1), $base + ['q_temp' => [], 'q_signs' => ['feels_hot']], auto: false));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('crying-baby', $this->boy(8), $base + ['q_temp' => ['39.5'], 'q_signs' => ['none'], 'q_pattern' => ['colic']], auto: false));
    }

    // ---------------------------------------------------- fever-infant-child

    public function test_fever_flow_has_a_bulging_fontanelle_red_flag_for_under_twos(): void
    {
        $flag = $this->redFlag('fever-infant-child', 'fontanelle_bulging');

        $this->assertSame('o_emergency', $flag['outcome']);
        $this->assertSame(['demo' => 'age_months', 'lt' => 24], $flag['when']);
    }

    public function test_fever_in_an_immunosuppressed_child_goes_to_the_emergency_department_now(): void
    {
        foreach ([8, 48, 120] as $months) {
            $answers = ['q_temp' => ['38.3'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']];
            $this->assertSame('o_ed_now_immuno', $this->outcomeOf('fever-infant-child', $this->child($months, ['immunosuppressed']), $answers), "{$months} months");
        }

        $answers = ['q_temp' => ['38.3'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']];
        $this->assertSame('o_ed_now_immuno', $this->outcomeOf('ear-pain-child', $this->child(48, ['immunosuppressed']), ['q_signs' => ['fever'], 'q_days' => ['lt_3']]));
    }

    public function test_low_temperature_in_children(): void
    {
        $answers = ['q_temp' => ['35.6'], 'q_days' => ['lt_1'], 'q_infant' => ['none'], 'q_signs' => ['none']];

        $this->assertSame('emergency_now', $this->levelOf('fever-infant-child', $this->boy(1), $answers, auto: false));
        $this->assertSame('emergency_department_now', $this->levelOf('fever-infant-child', $this->boy(30), $answers, auto: false));
        $this->assertSame('emergency_department_now', $this->levelOf('fever-infant-child', $this->boy(100), ['q_temp' => ['35.6'], 'q_days' => ['lt_1'], 'q_signs' => ['none']], auto: false));
    }

    public function test_fever_with_a_rash_is_same_day_in_both_fever_flows(): void
    {
        $this->assertSame('urgent_same_day', $this->levelOf('fever-infant-child', $this->boy(60), ['q_temp' => ['38.5'], 'q_days' => ['lt_1'], 'q_signs' => ['rash']], auto: false));

        $answers = ['i_glass' => ['seen'], 'q_type' => ['blotchy'], 'q_fever' => ['yes'], 'q_behaviour' => ['normal']];
        $this->assertSame('urgent_same_day', $this->levelOf('rash-with-fever-child', $this->boy(60), $answers, auto: false));
    }

    // ------------------------------------------------- rash-with-fever-child

    public function test_rash_flow_red_flags_match_the_fever_flow(): void
    {
        foreach (['cry', 'fontanelle_bulging', 'no_urine', 'no_urine_older'] as $code) {
            $this->assertSame('o_emergency', $this->redFlag('rash-with-fever-child', $code)['outcome'], $code);
        }
    }

    public function test_rash_flow_unsure_fever_in_a_young_infant_is_an_emergency(): void
    {
        $answers = ['i_glass' => ['seen'], 'q_type' => ['none'], 'q_fever' => ['unsure'], 'q_behaviour' => ['normal']];

        $this->assertSame('emergency_now', $this->levelOf('rash-with-fever-child', $this->boy(1), $answers, auto: false));
    }

    // ------------------------------------------------------- child-breathing

    public function test_child_asthma_with_the_reliever_not_helping_is_a_194_emergency(): void
    {
        $answers = ['q_pattern' => ['asthma'], 'q_better_calm' => ['yes']];

        $this->assertSame('emergency_now', $this->levelOf('child-breathing', $this->boy(84), $answers, auto: false));
    }

    public function test_child_croup_is_same_day_and_fever_with_fast_breathing_is_same_day(): void
    {
        $this->assertSame('urgent_same_day', $this->levelOf('child-breathing', $this->boy(30), ['q_pattern' => ['barking'], 'q_better_calm' => ['yes']], auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('child-breathing', $this->boy(60), ['q_pattern' => ['cold', 'fever'], 'q_better_calm' => ['yes']], auto: false));
    }

    // -------------------------------------------- diabetes in children's flows

    public function test_vomiting_in_a_diabetic_child_is_same_day_with_ketone_escalation(): void
    {
        $demo = $this->child(84, ['diabetes']);
        $base = ['q_signs' => ['none'], 'q_duration' => ['lt_24h']];

        $this->assertSame('urgent_same_day', $this->levelOf('vomiting-diarrhoea-child', $demo, $base + ['q_diabetes' => ['none']], auto: false));
        $this->assertSame('emergency_department_now', $this->levelOf('vomiting-diarrhoea-child', $demo, $base + ['q_diabetes' => ['ketones_mid']], auto: false));
        foreach (['deep_breath', 'acetone', 'ketones_high'] as $sign) {
            $this->assertSame('emergency_now', $this->levelOf('vomiting-diarrhoea-child', $demo, $base + ['q_diabetes' => [$sign]], auto: false), $sign);
        }
    }

    public function test_abdominal_pain_in_a_diabetic_child_never_reaches_home_care(): void
    {
        $demo = $this->child(84, ['diabetes']);

        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain-child', $demo, ['q_signs' => ['none'], 'q_pain' => ['2'], 'q_diabetes' => ['none']], auto: false));
        $this->assertSame('emergency_department_now', $this->levelOf('abdominal-pain-child', $demo, ['q_signs' => ['vomiting'], 'q_pain' => ['2'], 'q_diabetes' => ['none']], auto: false));
        $this->assertSame('emergency_now', $this->levelOf('abdominal-pain-child', $demo, ['q_signs' => ['vomiting'], 'q_pain' => ['2'], 'q_diabetes' => ['deep_breath']], auto: false));
    }

    public function test_abdominal_pain_child_intussusception_flag_does_not_need_blood(): void
    {
        $label = $this->redFlag('abdominal-pain-child', 'intussusception')['label'];

        $this->assertStringNotContainsString('крв', $label);
        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain-child', $this->boy(30), ['q_signs' => ['blood_stool'], 'q_pain' => ['2']], auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain-child', $this->boy(84), ['q_signs' => ['fever', 'urinary'], 'q_pain' => ['3']], auto: false));
    }

    // ------------------------------------------- head-injury-child and burns

    public function test_head_injury_child_new_red_flags_and_infant_rules(): void
    {
        $this->assertSame('o_emergency', $this->redFlag('head-injury-child', 'fontanelle_bulging')['outcome']);
        $this->assertSame('emergency_now', $this->flow('head-injury-child')['outcomes'][$this->redFlag('head-injury-child', 'neck')['outcome']]['level']);

        $calm = ['q_after' => ['none'], 'q_risk' => ['none']];
        $this->assertSame('urgent_same_day', $this->levelOf('head-injury-child', $this->boy(4), $calm, auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('head-injury-child', $this->boy(9), ['q_after' => ['none'], 'q_risk' => ['not_witnessed']], auto: false));
        $this->assertSame('self_care_with_safety_net', $this->levelOf('head-injury-child', $this->boy(30), $calm, auto: false));
        $this->assertSame('emergency_department_now', $this->levelOf('head-injury-child', $this->boy(100), ['q_after' => ['memory'], 'q_risk' => ['none']], auto: false));
    }

    public function test_any_burn_in_a_baby_under_one_is_same_day(): void
    {
        $answers = ['i_first_aid' => ['seen'], 'q_where' => ['other'], 'q_look' => ['red_only']];

        $this->assertSame('urgent_same_day', $this->levelOf('burns', $this->boy(5), $answers, auto: false));
        $this->assertSame('self_care_with_safety_net', $this->levelOf('burns', $this->boy(30), $answers, auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('burns', $this->demo(70 * 12), ['i_first_aid' => ['seen'], 'q_where' => ['other'], 'q_look' => ['blisters_small']], auto: false));
    }
}
