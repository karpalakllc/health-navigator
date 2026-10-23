<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Safety fixes of 2026-10-23 (CHANGES-2026-10-23 in the clinical review
 * pack), pending clinician review: pregnancy, emergency contraception,
 * vaginal bleeding, sleep, breast and blood-pressure flows.
 */
class PregnancyAndSexualHealthTest extends TestCase
{
    use WalksFlows;

    /** @return array<string, mixed> */
    private function pregnant(): array
    {
        return $this->demo(30 * 12, 'female', 'pregnant');
    }

    public function test_spotting_in_pregnancy_is_same_day_at_every_stage_like_the_bleeding_flow(): void
    {
        foreach (['early', 'mid', 'term'] as $stage) {
            $this->assertSame('o_urgent_now_1', $this->outcomeOf('pregnancy-concerns', $this->pregnant(), ['q_stage' => [$stage], 'q_preg_signs' => ['spotting']]), $stage);
        }

        $this->assertSame('urgent_same_day', $this->levelOf('vaginal-bleeding', $this->pregnant(), ['q_situation' => ['pregnant'], 'q_signs' => ['none']]));
    }

    public function test_pregnancy_concerns_has_an_ectopic_red_flag_shown_to_everyone_who_chose_the_flow(): void
    {
        $flag = $this->redFlag('pregnancy-concerns', 'ectopic');

        $this->assertArrayNotHasKey('when', $flag);
        $this->assertSame('emergency_now', $this->flow('pregnancy-concerns')['outcomes'][$flag['outcome']]['level']);
        $this->assertStringContainsString('рамото', $flag['label']);
        $this->assertStringContainsString('несвестица', $flag['label']);
    }

    public function test_contractions_before_37_weeks_and_a_blow_to_the_bump_are_same_day(): void
    {
        foreach (['early', 'mid'] as $stage) {
            $this->assertSame('o_urgent_now_1', $this->outcomeOf('pregnancy-concerns', $this->pregnant(), ['q_stage' => [$stage], 'q_preg_signs' => ['contractions']]), $stage);
        }

        $this->assertSame('o_urgent_now_1', $this->outcomeOf('pregnancy-concerns', $this->pregnant(), ['q_stage' => ['mid'], 'q_preg_signs' => ['blow']]));
        $this->assertSame('o_labour_1', $this->outcomeOf('pregnancy-concerns', $this->pregnant(), ['q_stage' => ['term'], 'q_preg_signs' => ['contractions']]));
    }

    public function test_itching_in_pregnancy_is_same_day(): void
    {
        $this->assertSame('urgent_same_day', $this->levelOf('pregnancy-concerns', $this->pregnant(), ['q_stage' => ['mid'], 'q_preg_signs' => ['itch']], auto: false));
    }

    public function test_suspected_postpartum_psychosis_reaches_the_crisis_outcome(): void
    {
        $postpartum = $this->demo(30 * 12, 'female', 'postpartum');

        $this->assertSame('global:crisis', $this->outcomeOf('pregnancy-concerns', $postpartum, ['q_stage' => ['postpartum'], 'q_post_signs' => ['unusual']]));
        $this->assertSame('global:crisis', $this->outcomeOf('pregnancy-concerns', $postpartum, ['q_stage' => ['postpartum'], 'q_post_signs' => ['mood', 'unusual']]));
        $this->assertSame('o_urgent_now_2', $this->outcomeOf('pregnancy-concerns', $postpartum, ['q_stage' => ['postpartum'], 'q_post_signs' => ['fever']]));

        $flag = $this->redFlag('sleep-problems', 'postpartum_psychosis');
        $this->assertSame('global:crisis', $flag['outcome']);
    }

    public function test_a_late_period_does_not_override_the_emergency_contraception_window(): void
    {
        $demo = $this->demo(25 * 12);

        $late = $this->outcomeOf('emergency-contraception', $demo, ['q_assault' => ['no'], 'q_hours' => ['lt_72'], 'q_late_period' => ['yes']]);
        $this->assertSame('o_pharm_late_1', $late);
        $this->assertSame('pharmacy_advice', $this->flow('emergency-contraception')['outcomes'][$late]['level']);

        $this->assertSame('o_pharm_1', $this->outcomeOf('emergency-contraception', $demo, ['q_assault' => ['no'], 'q_hours' => ['lt_72'], 'q_late_period' => ['no']]));
        $this->assertSame('o_urgent_gyn_1', $this->outcomeOf('emergency-contraception', $demo, ['q_assault' => ['prefer_not'], 'q_hours' => ['72_120'], 'q_late_period' => ['yes']]));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('emergency-contraception', $demo, ['q_assault' => ['no'], 'q_hours' => ['gt_120'], 'q_late_period' => ['no']], auto: false));
    }

    public function test_vaginal_bleeding_escalations(): void
    {
        $demo = $this->demo(30 * 12);

        $this->assertSame('o_urgent_1', $this->outcomeOf('vaginal-bleeding', $demo, ['q_situation' => ['heavy_period'], 'q_signs' => ['anticoag', 'soaking']]));
        $this->assertSame('o_urgent_2', $this->outcomeOf('vaginal-bleeding', $demo, ['q_situation' => ['between'], 'q_signs' => ['fever']]));
        $this->assertSame(['demo' => 'pregnancy', 'ne' => 'not_asked'], $this->redFlag('vaginal-bleeding', 'preg_heavy')['when']);
    }

    public function test_breathless_lying_flat_is_same_day_and_targeted_sleep_outcomes_are_reachable(): void
    {
        $this->assertSame('urgent_same_day', $this->levelOf('sleep-problems', $this->demo(50 * 12), ['q_problem' => ['breathless_night']]));
        $this->assertSame('o_gp_5', $this->autoOutcome('sleep-problems', $this->demo(70 * 12), ['q_problem' => ['sleepy_day']]));
        $this->assertSame('o_gp_4', $this->autoOutcome('sleep-problems', $this->demo(30 * 12, 'female', 'postpartum'), ['q_problem' => ['low_mood']]));
    }

    public function test_breast_changes_without_fever_need_a_doctor_within_two_days(): void
    {
        $answers = ['q_cycle_pain' => ['no'], 'q_breastfeeding' => ['no'], 'q_fever' => ['no']];

        $this->assertSame('o_doc48_2', $this->outcomeOf('breast-lump', $this->demo(60 * 12), ['q_changes' => ['red_hot']] + $answers));
        $this->assertSame('o_doc48_2', $this->outcomeOf('breast-lump', $this->demo(40 * 12), ['q_changes' => ['nipple_skin']] + $answers));
        $this->assertSame('o_self_bf_1', $this->outcomeOf('breast-lump', $this->demo(30 * 12), ['q_changes' => ['red_hot'], 'q_cycle_pain' => ['no'], 'q_breastfeeding' => ['yes'], 'q_fever' => ['no']]));
    }

    public function test_severe_blood_pressure_in_pregnancy_goes_to_hospital_now(): void
    {
        $answers = ['i_how' => ['seen'], 'q_systolic' => ['165'], 'q_diastolic' => ['100'], 'q_context' => ['none']];
        $this->assertSame('emergency_department_now', $this->levelOf('high-blood-pressure', $this->pregnant(), $answers, auto: false));

        $answers['q_systolic'] = ['145'];
        $this->assertSame('urgent_same_day', $this->levelOf('high-blood-pressure', $this->pregnant(), $answers, auto: false));
    }
}
