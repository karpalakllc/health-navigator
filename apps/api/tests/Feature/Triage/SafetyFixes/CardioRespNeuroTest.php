<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Safety fixes of 2026-10-23 (CHANGES-2026-10-23 in the clinical review
 * pack), pending clinician review: breathing, heart, confusion, head injury,
 * falls, fainting, allergy and headache flows.
 */
class CardioRespNeuroTest extends TestCase
{
    use WalksFlows;

    private function adult(array $conditions = []): array
    {
        return $this->demo(35 * 12, 'male', 'not_asked', $conditions);
    }

    public function test_asthma_still_breathless_after_the_reliever_is_an_emergency(): void
    {
        $this->assertSame('emergency_now', $this->levelOf('asthma-attack', $this->adult(), ['q_response' => ['partial'], 'q_peak' => ['no_meter']]));
        $this->assertSame('emergency_now', $this->levelOf('asthma-attack', $this->demo(3 * 12, 'male'), ['q_response' => ['partial'], 'q_peak' => ['no_meter']]));
        // Relief that wears off quickly stays same day; full relief → 1–2 days.
        $this->assertSame('urgent_same_day', $this->levelOf('asthma-attack', $this->adult(), ['q_response' => ['short'], 'q_peak' => ['no_meter']]));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('asthma-attack', $this->adult(), ['q_response' => ['full'], 'q_peak' => ['no_meter']]));
    }

    public function test_asthma_has_a_red_flag_for_a_child_working_hard_to_breathe(): void
    {
        $flag = $this->redFlag('asthma-attack', 'child_working_hard');
        $this->assertSame('o_emergency', $flag['outcome']);
    }

    public function test_breathlessness_with_saturation_of_92_or_less_is_an_emergency(): void
    {
        $answers = ['q_context' => ['none'], 'q_dvt_risk' => ['none'], 'q_onset' => ['days'], 'q_spo2' => ['le92']];
        $this->assertSame('emergency_now', $this->levelOf('shortness-of-breath', $this->adult(), $answers));
        $answers['q_spo2'] = ['93_94'];
        $this->assertSame('urgent_same_day', $this->levelOf('shortness-of-breath', $this->adult(), $answers));
    }

    public function test_breathlessness_in_asthma_with_the_reliever_not_helping_is_an_emergency(): void
    {
        foreach (['not_helped', 'no_inhaler'] as $answer) {
            $this->assertSame('emergency_now', $this->levelOf('shortness-of-breath', $this->adult(), ['q_context' => ['asthma'], 'q_reliever' => [$answer]]), $answer);
        }
        $this->assertSame('urgent_same_day', $this->levelOf('shortness-of-breath', $this->adult(), ['q_context' => ['asthma'], 'q_reliever' => ['helped'], 'q_dvt_risk' => ['none'], 'q_onset' => ['days'], 'q_spo2' => ['no_device']]));
    }

    public function test_breathlessness_with_newer_clot_risks_escalates(): void
    {
        $base = ['q_context' => ['none'], 'q_dvt_risk' => ['hormones'], 'q_spo2' => ['no_device']];
        $this->assertSame('emergency_now', $this->levelOf('shortness-of-breath', $this->adult(), $base + ['q_onset' => ['days']]));
        $this->assertSame('urgent_same_day', $this->levelOf('shortness-of-breath', $this->adult(), $base + ['q_onset' => ['weeks']]));
    }

    public function test_ongoing_fast_palpitations_go_to_the_emergency_department_now(): void
    {
        $this->assertSame('emergency_department_now', $this->levelOf('palpitations', $this->adult(), ['q_now' => ['now_fast'], 'q_pulse' => [], 'q_context' => ['none']]));
        $this->assertSame('emergency_department_now', $this->levelOf('palpitations', $this->adult(), ['q_now' => ['now_mild'], 'q_pulse' => ['125'], 'q_context' => ['none']]));
        $this->assertSame('urgent_same_day', $this->levelOf('palpitations', $this->adult(), ['q_now' => ['now_mild'], 'q_pulse' => [], 'q_context' => ['triggers']]));
        $this->assertSame('emergency_now', $this->levelOf('palpitations', $this->adult(), ['q_now' => ['now_fast'], 'q_pulse' => ['160']]));
    }

    public function test_palpitations_after_birth_follow_the_pregnancy_rule(): void
    {
        $answers = ['q_now' => ['past'], 'q_pulse' => [], 'q_context' => ['none']];
        $this->assertSame('o_doc48_1_preg', $this->outcomeOf('palpitations', $this->demo(30 * 12, 'female', 'postpartum'), $answers));
    }

    public function test_exertional_chest_pain_never_reaches_self_care(): void
    {
        foreach (['now', 'today', 'days', 'weeks'] as $duration) {
            $answers = ['q_pattern' => ['exertion'], 'q_cardiac_risk' => ['none'], 'q_duration' => [$duration]];
            $this->assertSame('urgent_same_day', $this->levelOf('chest-pain', $this->adult(), $answers), $duration);
        }
        // Chest-wall pain after a knock or strain stays self-care.
        $answers = ['q_pattern' => ['press_spot'], 'q_cardiac_risk' => ['none'], 'q_duration' => ['days']];
        $this->assertSame('self_care_with_safety_net', $this->levelOf('chest-pain', $this->adult(), $answers));
    }

    public function test_chest_pain_after_stimulants_and_resolved_pain_today_escalate(): void
    {
        $this->assertSame('urgent_same_day', $this->levelOf('chest-pain', $this->adult(), ['q_pattern' => ['brief_gone'], 'q_cardiac_risk' => ['cocaine'], 'q_duration' => ['today']]));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('chest-pain', $this->adult(), ['q_pattern' => ['brief_gone'], 'q_cardiac_risk' => ['none'], 'q_duration' => ['today']]));
        $this->assertSame('emergency_now', $this->levelOf('chest-pain', $this->adult(), ['q_pattern' => ['sharp_breath'], 'q_dvt_risk' => ['cancer']]));
    }

    public function test_sudden_or_fluctuating_confusion_is_an_emergency(): void
    {
        foreach (['hours_days', 'fluctuating'] as $onset) {
            $this->assertSame('emergency_now', $this->levelOf('confusion-older', $this->demo(80 * 12), ['q_onset' => [$onset], 'q_clues' => ['none']]), $onset);
        }
        $this->assertSame('urgent_same_day', $this->levelOf('confusion-older', $this->demo(80 * 12), ['q_onset' => ['months'], 'q_clues' => ['not_drinking']]));
        $this->assertSame('see_gp_this_week', $this->levelOf('confusion-older', $this->demo(80 * 12), ['q_onset' => ['months'], 'q_clues' => ['none']]));
        // Fast sugar only in the hypo outcome.
        $flow = $this->flow('confusion-older');
        $this->assertSame('o_emergency_hypo', $this->redFlag('confusion-older', 'hypo')['outcome']);
        $this->assertStringNotContainsString('шеќер', implode(' ', $flow['outcomes']['o_emergency']['do_now']));
    }

    public function test_head_injury_confusion_and_neck_pain_are_emergencies(): void
    {
        $this->assertSame('o_emergency', $this->redFlag('head-injury-adult', 'confused')['outcome']);
        $this->assertSame('o_emergency', $this->redFlag('head-injury-adult', 'neck')['outcome']);
        $this->assertSame('emergency_now', $this->levelOf('head-injury-adult', $this->adult(), ['q_after' => ['behaviour'], 'q_risk' => ['none'], 'q_when' => ['today']]));
    }

    public function test_head_injury_risk_features_go_to_the_emergency_department_now(): void
    {
        $this->assertSame('emergency_department_now', $this->levelOf('head-injury-adult', $this->adult(), ['q_after' => ['vomiting'], 'q_risk' => ['none'], 'q_when' => ['today']]));
        $this->assertSame('emergency_department_now', $this->levelOf('head-injury-adult', $this->adult(), ['q_after' => ['none'], 'q_risk' => ['anticoag'], 'q_when' => ['today']]));
        $this->assertSame('urgent_same_day', $this->levelOf('head-injury-adult', $this->adult(), ['q_after' => ['dizzy'], 'q_risk' => ['none'], 'q_when' => ['today']]));
    }

    public function test_falls_blackout_with_injury_or_new_confusion_is_an_emergency(): void
    {
        $old = $this->demo(80 * 12);
        $this->assertSame('emergency_now', $this->levelOf('falls-older', $old, ['q_cause' => ['blackout'], 'q_after' => ['pain'], 'q_history' => ['none']]));
        $this->assertSame('emergency_now', $this->levelOf('falls-older', $old, ['q_cause' => ['trip'], 'q_after' => ['confused_new'], 'q_history' => ['none']]));
        $this->assertSame('urgent_same_day', $this->levelOf('falls-older', $old, ['q_cause' => ['blackout'], 'q_after' => ['none'], 'q_history' => ['none']]));
        $this->assertSame('o_emergency', $this->redFlag('falls-older', 'neck_back')['outcome']);
    }

    public function test_fainting_escalations(): void
    {
        foreach (['hurt', 'lying_down', 'blood_loss', 'ectopic'] as $code) {
            $this->assertSame('o_emergency', $this->redFlag('dizziness-fainting', $code)['outcome'], $code);
        }
        // Near-faint in a high-risk person → same day.
        $this->assertSame('urgent_same_day', $this->levelOf('dizziness-fainting', $this->demo(70 * 12, 'male', 'not_asked'), ['q_what' => ['nearly'], 'q_context' => ['standing_up']]));
        // A faint with a clear trigger is not self-care.
        $this->assertSame('see_gp_this_week', $this->levelOf('dizziness-fainting', $this->adult(), ['q_what' => ['fainted'], 'q_context' => ['trigger']]));
        // Sudden one-sided hearing loss → same day.
        $this->assertSame('urgent_same_day', $this->levelOf('dizziness-fainting', $this->adult(), ['q_what' => ['spinning'], 'q_context' => ['hearing']]));
        // Postpartum faint → same day.
        $this->assertSame('o_urgent_1_preg', $this->outcomeOf('dizziness-fainting', $this->demo(30 * 12, 'female', 'postpartum'), ['q_what' => ['fainted'], 'q_context' => ['trigger']]));
    }

    public function test_lip_or_face_swelling_is_an_emergency_like_the_global_flag(): void
    {
        $this->assertSame('emergency_now', $this->levelOf('allergic-reaction', $this->adult(), ['q_signs' => ['lip_face']]));
        $this->assertSame('emergency_now', $this->levelOf('allergic-reaction', $this->adult(), ['q_signs' => ['hives_wide'], 'q_has_autoinjector' => ['yes']]));
        $this->assertSame('urgent_same_day', $this->levelOf('allergic-reaction', $this->adult(), ['q_signs' => ['hives_wide'], 'q_has_autoinjector' => ['no']]));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('allergic-reaction', $this->demo(6, 'male'), ['q_signs' => ['itch_eyes_nose'], 'q_has_autoinjector' => ['no']]));
    }

    public function test_headache_clear_routing_fixes(): void
    {
        $base = ['q_extras' => ['none'], 'q_risk' => ['none']];
        $this->assertSame('urgent_same_day', $this->levelOf('headache', $this->demo(55 * 12, 'male', 'not_asked'), $base + ['q_type' => ['new_pattern'], 'q_pain' => ['5']]));
        $this->assertSame('urgent_same_day', $this->levelOf('headache', $this->adult(), $base + ['q_type' => ['usual'], 'q_pain' => ['9']]));
        $this->assertSame('urgent_same_day', $this->levelOf('headache', $this->adult(['immunosuppressed']), ['q_type' => ['usual'], 'q_extras' => ['fever'], 'q_risk' => ['none'], 'q_pain' => ['4']]));
        $this->assertSame('see_doctor_24_48h', $this->levelOf('headache', $this->demo(30 * 12, 'female', 'postpartum'), $base + ['q_type' => ['usual'], 'q_pain' => ['4']]));
        $this->assertSame('pharmacy_advice', $this->levelOf('headache', $this->adult(), $base + ['q_type' => ['usual'], 'q_pain' => ['4']]));
    }
}
