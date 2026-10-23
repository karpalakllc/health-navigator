<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Safety fixes of 2026-10-23 (CHANGES-2026-10-23 in the clinical review
 * pack), pending clinician review: general, eye-problems, back-pain,
 * abdominal-pain, toothache, limb-injury.
 */
class GeneralAndOtherFlowsTest extends TestCase
{
    use WalksFlows;

    /** @return array<string, list<string>> */
    private function general(string $severity, string $duration): array
    {
        return ['concern' => ['general'], 'severity' => [$severity], 'duration' => [$duration]];
    }

    public function test_general_very_severe_goes_to_the_emergency_department_now(): void
    {
        foreach ([1, 30 * 12, 70 * 12] as $months) {
            $this->assertSame('emergency_department_now', $this->levelOf('general', $this->demo($months), $this->general('very_severe', 'over_week'), auto: false), (string) $months);
        }
    }

    public function test_general_severe_and_recent_or_in_a_risk_group_is_same_day(): void
    {
        $this->assertSame('urgent_same_day', $this->levelOf('general', $this->demo(30 * 12, 'male'), $this->general('severe', 'under_24h'), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('general', $this->demo(70 * 12, 'male'), $this->general('severe', '1_7_days'), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('general', $this->demo(6), $this->general('severe', '1_7_days'), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('general', $this->demo(30 * 12, 'female', 'pregnant'), $this->general('severe', '1_7_days'), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('general', $this->demo(30 * 12, 'male', 'not_asked', ['immunosuppressed']), $this->general('severe', 'over_week'), auto: false));
        // Severe for days in a healthy adult: a doctor within 1–2 days.
        $this->assertSame('see_doctor_24_48h', $this->levelOf('general', $this->demo(30 * 12, 'male'), $this->general('severe', '1_7_days'), auto: false));
    }

    public function test_general_young_infant(): void
    {
        $this->assertSame('emergency_department_now', $this->levelOf('general', $this->demo(1), $this->general('severe', 'under_24h'), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('general', $this->demo(1), $this->general('mild', 'under_24h'), auto: false));
    }

    public function test_general_mild_short_stays_self_care_and_long_goes_to_the_gp(): void
    {
        $this->assertSame('self_care_with_safety_net', $this->levelOf('general', $this->demo(40 * 12, 'male'), $this->general('mild', 'under_24h'), auto: false));
        $this->assertSame('see_gp_this_week', $this->levelOf('general', $this->demo(40 * 12, 'male'), $this->general('mild', 'over_week'), auto: false));
    }

    public function test_general_has_sepsis_and_vomited_blood_red_flags(): void
    {
        $this->assertSame('nhs-sepsis', $this->redFlag('general', 'sepsis_signs')['source']);
        $this->assertSame('nhs-vomiting-blood', $this->redFlag('general', 'vomiting_blood')['source']);
    }

    public function test_red_eye_with_vision_change_light_pain_or_a_stuck_object_goes_to_the_emergency_department(): void
    {
        foreach (['blurred', 'light', 'foreign_body', 'pupil_dark'] as $feature) {
            $this->assertSame('emergency_department_now', $this->levelOf('eye-problems', $this->demo(40 * 12, 'male'), ['q_features' => [$feature]]), $feature);
        }
        $this->assertSame('o_ed_now_preg', $this->outcomeOf('eye-problems', $this->demo(30 * 12, 'female', 'pregnant'), ['q_features' => ['blurred']]));
    }

    public function test_painful_red_eye_or_contact_lenses_are_same_day(): void
    {
        foreach (['pain', 'lens'] as $feature) {
            $this->assertSame('urgent_same_day', $this->levelOf('eye-problems', $this->demo(40 * 12, 'male'), ['q_features' => [$feature]]), $feature);
        }
        $this->assertSame('see_doctor_24_48h', $this->levelOf('eye-problems', $this->demo(40 * 12, 'male'), ['q_features' => ['injury_minor']]));
    }

    public function test_back_pain_tearing_red_flag_no_longer_needs_fainting(): void
    {
        $flag = $this->redFlag('back-pain', 'tearing');
        $this->assertStringNotContainsString('со несвестица', $flag['label']);
        $this->assertSame('o_emergency', $flag['outcome']);
        $this->assertStringNotContainsString('влошува', $this->redFlag('back-pain', 'both_legs')['label']);
    }

    public function test_back_pain_with_cancer_or_new_leg_weakness_is_same_day(): void
    {
        foreach (['cancer', 'leg_weakness'] as $feature) {
            $this->assertSame('urgent_same_day', $this->levelOf('back-pain', $this->demo(50 * 12, 'male'), ['q_features' => [$feature], 'q_duration' => ['lt_6w']]), $feature);
        }
    }

    /** @return array<string, list<string>> */
    private function abdomen(string $where, array $with, int $pain, string $duration = 'lt_24h'): array
    {
        return ['q_where' => [$where], 'q_with' => $with, 'q_pain' => [(string) $pain], 'q_duration' => [$duration]];
    }

    public function test_upper_middle_pain_goes_to_pharmacy_only_when_mild_and_low_risk(): void
    {
        $this->assertSame('pharmacy_advice', $this->levelOf('abdominal-pain', $this->demo(30 * 12, 'male'), $this->abdomen('upper_middle', ['none'], 3), auto: false));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('abdominal-pain', $this->demo(30 * 12, 'male'), $this->abdomen('upper_middle', ['none'], 7), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain', $this->demo(55 * 12, 'male'), $this->abdomen('upper_middle', ['none'], 3), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain', $this->demo(35 * 12, 'male', 'not_asked', ['diabetes']), $this->abdomen('upper_middle', ['none'], 3, '1_3d'), auto: false));
    }

    public function test_abdominal_pain_other_escalations(): void
    {
        $this->assertSame('emergency_department_now', $this->levelOf('abdominal-pain', $this->demo(30 * 12, 'male'), $this->abdomen('flank', ['fever'], 5, '1_3d'), auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain', $this->demo(30 * 12, 'female'), $this->abdomen('lower', ['fever'], 5, '1_3d'), auto: false));
        $this->assertSame('o_urgent_postpartum', $this->outcomeOf('abdominal-pain', $this->demo(30 * 12, 'female', 'postpartum'), $this->abdomen('lower', ['none'], 3, '1_3d')));
        $this->assertSame('urgent_same_day', $this->levelOf('abdominal-pain', $this->demo(70 * 12, 'male'), $this->abdomen('lower', ['none'], 2), auto: false));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('abdominal-pain', $this->demo(70 * 12, 'male'), $this->abdomen('lower', ['none'], 2, '1_3d'), auto: false));
    }

    public function test_toothache_mouth_swelling_is_a_red_flag_and_facial_swelling_is_same_day(): void
    {
        $this->assertSame('o_emergency', $this->redFlag('toothache', 'mouth_swelling')['outcome']);
        $this->assertSame('urgent_same_day', $this->levelOf('toothache', $this->demo(30 * 12), ['q_signs' => ['face_swelling'], 'q_duration' => ['lt_2d']], auto: false));
        $this->assertSame('o_urgent_tooth', $this->outcomeOf('toothache', $this->demo(30 * 12), ['q_signs' => ['knocked_out'], 'q_duration' => ['lt_2d']]));
    }

    public function test_limb_compartment_signs_hot_joint_and_young_child_without_injury(): void
    {
        $this->assertSame('emergency_department_now', $this->levelOf('limb-injury', $this->demo(30 * 12), ['q_cause' => ['injury'], 'q_signs' => ['compartment'], 'q_pain' => ['5']], auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('limb-injury', $this->demo(30 * 12), ['q_cause' => ['injury'], 'q_signs' => ['red_hot_joint'], 'q_pain' => ['4']], auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('limb-injury', $this->demo(30), ['q_cause' => ['no_injury'], 'q_signs' => ['none'], 'q_pain' => ['3']], auto: false));
        $this->assertSame('see_gp_this_week', $this->levelOf('limb-injury', $this->demo(30 * 12), ['q_cause' => ['no_injury'], 'q_signs' => ['none'], 'q_pain' => ['3']], auto: false));
    }
}
