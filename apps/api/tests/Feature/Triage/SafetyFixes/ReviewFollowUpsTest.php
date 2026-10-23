<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Follow-ups from the independent review of the 2026-10-23 safety fixes
 * (CHANGES-2026-10-23 § 4): branch order that hid a more urgent rule, and
 * floors the first round lowered without saying so. Pending clinician review.
 */
class ReviewFollowUpsTest extends TestCase
{
    use WalksFlows;

    public function test_a_diabetic_child_vomiting_after_a_head_injury_still_goes_to_the_emergency_department_now(): void
    {
        $demo = $this->demo(6 * 12, 'male', 'not_pregnant', ['diabetes']);

        $this->assertSame('o_ed_now_head', $this->autoOutcome('vomiting-diarrhoea-child', $demo, ['q_signs' => ['head_injury']]));
    }

    public function test_a_diabetic_baby_under_three_months_still_goes_now(): void
    {
        $demo = $this->demo(1, 'male', 'not_pregnant', ['diabetes']);

        $this->assertSame('o_urgent_now_1', $this->autoOutcome('vomiting-diarrhoea-child', $demo, []));
    }

    public function test_immunosuppression_with_fever_and_breathlessness_goes_to_the_emergency_department_now(): void
    {
        $demo = $this->demo(40 * 12, 'male', 'not_pregnant', ['immunosuppressed']);

        $this->assertSame('o_ed_immuno', $this->autoOutcome('cough-cold-flu', $demo, ['q_symptoms' => ['sob_mild', 'fever_high']]));
    }

    public function test_peak_flow_under_half_after_a_full_response_is_still_same_day(): void
    {
        $answers = ['i_plan' => ['seen'], 'q_response' => ['full'], 'q_peak' => ['lt_50']];

        $this->assertSame('urgent_same_day', $this->levelOf('asthma-attack', $this->demo(30 * 12), $answers));
    }

    public function test_an_immunosuppressed_child_with_a_temperature_under_38_keeps_the_one_to_two_day_floor(): void
    {
        $demo = $this->demo(5 * 12, 'male', 'not_pregnant', ['immunosuppressed']);

        $this->assertSame('o_doc48_1', $this->autoOutcome('fever-infant-child', $demo, ['q_temp' => ['37.8']]));
    }

    public function test_crying_baby_of_three_to_five_months_warm_but_unmeasured_matches_the_fever_flow(): void
    {
        $answers = ['q_temp' => [], 'q_signs' => ['feels_hot'], 'q_pattern' => ['other'], 'q_carer_ok' => ['yes']];

        $this->assertSame('o_urgent_now_2', $this->outcomeOf('crying-baby', $this->demo(4, 'male'), $answers));
        $this->assertSame('o_doc48_fever', $this->outcomeOf('crying-baby', $this->demo(8, 'male'), $answers));
    }

    public function test_rash_with_an_unsure_fever_is_treated_as_fever(): void
    {
        $answers = ['q_type' => ['blotchy'], 'q_fever' => ['unsure'], 'q_behaviour' => ['normal']];

        $this->assertSame('o_urgent_fever', $this->autoOutcome('rash-with-fever-child', $this->demo(2 * 12, 'male'), $answers));
    }

    public function test_the_fainting_ectopic_flag_shows_whenever_pregnancy_was_asked(): void
    {
        $this->assertSame(['demo' => 'pregnancy', 'ne' => 'not_asked'], $this->redFlag('dizziness-fainting', 'ectopic')['when']);
    }

    public function test_a_very_severe_symptom_in_a_baby_under_three_months_gets_the_infant_outcome(): void
    {
        $answers = ['concern' => ['general'], 'severity' => ['very_severe'], 'duration' => ['under_24h']];

        $this->assertSame('ed_now_infant', $this->outcomeOf('general', $this->demo(1, 'male'), $answers));
    }
}
