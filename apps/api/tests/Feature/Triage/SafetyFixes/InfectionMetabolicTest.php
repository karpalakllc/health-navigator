<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Safety fixes of 2026-10-23 (CHANGES-2026-10-23 in the clinical review
 * pack), pending clinician review: diabetes, fever, infections and rashes.
 */
class InfectionMetabolicTest extends TestCase
{
    use WalksFlows;

    // ------------------------------------------------------------ diabetes

    public function test_the_dka_red_flag_takes_any_one_sign_and_leads_to_the_high_sugar_emergency(): void
    {
        $flag = $this->redFlag('diabetes-blood-sugar', 'dka');

        $this->assertStringContainsString('барем еден', $flag['label']);
        $this->assertSame('o_emergency_1', $flag['outcome']);
        $this->assertSame('o_emergency_1', $this->redFlag('diabetes-blood-sugar', 'very_high_dehydrated')['outcome']);
    }

    public function test_the_high_sugar_emergency_gives_no_glucagon_advice(): void
    {
        $outcome = $this->flow('diabetes-blood-sugar')['outcomes']['o_emergency_1'];

        $this->assertSame('emergency_now', $outcome['level']);
        $this->assertStringNotContainsString('глукагон', mb_strtolower(implode(' ', $outcome['do_now'])));
    }

    public function test_high_sugar_with_vomiting_is_an_emergency(): void
    {
        foreach ([[], ['type1']] as $extra) {
            $answers = ['q_problem' => ['high'], 'q_glucose' => ['14'], 'q_ketones' => ['lt_0_6'], 'q_high_signs' => [...$extra, 'vomiting']];
            $this->assertSame('emergency_now', $this->levelOf('diabetes-blood-sugar', $this->demo(40 * 12), $answers, auto: false));
        }
    }

    public function test_type_1_with_unmeasured_ketones_is_not_plain_self_care(): void
    {
        $answers = ['q_problem' => ['high'], 'q_glucose' => [], 'q_ketones' => ['not_measured'], 'q_high_signs' => ['type1']];

        $this->assertSame('o_urgent_5', $this->outcomeOf('diabetes-blood-sugar', $this->demo(30 * 12), $answers));
    }

    public function test_low_sugar_not_rising_after_two_treatments_is_an_emergency_with_hypo_advice(): void
    {
        $answers = ['q_problem' => ['low'], 'q_glucose' => ['3.1'], 'q_treated' => ['still_low']];

        $this->assertSame('o_emergency_low', $this->outcomeOf('diabetes-blood-sugar', $this->demo(50 * 12), $answers));
        $doNow = implode(' ', $this->flow('diabetes-blood-sugar')['outcomes']['o_emergency_low']['do_now']);
        $this->assertStringContainsString('брз шеќер', $doNow);
    }

    public function test_mild_high_sugar_ends_in_its_own_self_care(): void
    {
        $answers = ['q_problem' => ['high'], 'q_glucose' => ['10'], 'q_ketones' => ['lt_0_6'], 'q_high_signs' => ['none']];

        $this->assertSame('o_self_high', $this->outcomeOf('diabetes-blood-sugar', $this->demo(50 * 12), $answers));
    }

    public function test_vomiting_with_diabetes_in_adults_is_same_day(): void
    {
        $base = ['q_signs' => ['none'], 'q_duration' => ['lt_24h'], 'q_vomit' => ['lt_2d']];

        $this->assertSame('o_urgent_diab', $this->outcomeOf('diarrhoea-vomiting-adult', $this->demo(30 * 12, conditions: ['diabetes']), $base + ['q_risk' => ['none']]));
        $this->assertSame('o_urgent_diab', $this->outcomeOf('diarrhoea-vomiting-adult', $this->demo(30 * 12), $base + ['q_risk' => ['diabetes']]));
        $this->assertSame('self_care_with_safety_net', $this->levelOf('diarrhoea-vomiting-adult', $this->demo(30 * 12), $base + ['q_risk' => ['none']], auto: false));
    }

    public function test_adult_dv_urgent_nhs_criteria_are_same_day(): void
    {
        $demo = $this->demo(30 * 12);
        foreach ([
            'cannot keep fluids' => ['q_signs' => ['no_fluids'], 'q_duration' => ['lt_24h'], 'q_vomit' => ['lt_2d']],
            'bloody diarrhoea' => ['q_signs' => ['bloody_diarrhoea'], 'q_duration' => ['lt_24h'], 'q_vomit' => ['no']],
            'vomiting 2 days' => ['q_signs' => ['none'], 'q_duration' => ['1_2d'], 'q_vomit' => ['ge_2d']],
        ] as $case => $answers) {
            $this->assertSame('urgent_same_day', $this->levelOf('diarrhoea-vomiting-adult', $demo, $answers + ['q_risk' => ['none']], auto: false), $case);
        }
    }

    // --------------------------------------------------------------- fever

    public function test_fever_with_chemotherapy_or_immunosuppression_goes_to_the_emergency_department_now(): void
    {
        $answers = ['q_temp' => ['38.4'], 'q_duration' => ['lt_3d'], 'q_situation' => ['chemo']];
        $this->assertSame('emergency_department_now', $this->levelOf('fever-adult', $this->demo(50 * 12), $answers, auto: false));

        $answers['q_situation'] = ['none'];
        $this->assertSame('emergency_department_now', $this->levelOf('fever-adult', $this->demo(50 * 12, conditions: ['immunosuppressed']), $answers, auto: false));

        $sore = ['q_features' => ['immuno_drugs', 'fever'], 'q_duration' => ['lt_1w']];
        $this->assertSame('emergency_department_now', $this->levelOf('sore-throat', $this->demo(40 * 12), $sore, auto: false));
        $this->assertSame('urgent_same_day', $this->levelOf('sore-throat', $this->demo(40 * 12), ['q_features' => ['immuno_drugs'], 'q_duration' => ['lt_1w']], auto: false));

        $cough = ['q_symptoms' => ['fever_high'], 'q_duration' => ['lt_1w'], 'q_trend' => ['same']];
        $this->assertSame('emergency_department_now', $this->levelOf('cough-cold-flu', $this->demo(40 * 12, conditions: ['immunosuppressed']), $cough, auto: false));
    }

    public function test_a_low_temperature_goes_to_the_emergency_department_now(): void
    {
        $answers = ['q_temp' => ['35.5'], 'q_duration' => ['lt_3d'], 'q_situation' => ['none']];

        $this->assertSame('o_ed_lowtemp', $this->outcomeOf('fever-adult', $this->demo(40 * 12), $answers));
    }

    public function test_postpartum_fever_and_unmeasured_fever_in_pregnancy_are_same_day(): void
    {
        $answers = ['q_temp' => [], 'q_duration' => ['lt_3d'], 'q_situation' => ['none']];

        $this->assertSame('o_urgent_5', $this->outcomeOf('fever-adult', $this->demo(30 * 12, 'female', 'postpartum'), $answers));
        $this->assertSame('o_urgent_5', $this->outcomeOf('fever-adult', $this->demo(30 * 12, 'female', 'pregnant'), $answers));
    }

    public function test_any_fever_at_65_plus_is_at_least_a_doctor_within_two_days(): void
    {
        $answers = ['q_temp' => ['38.2'], 'q_duration' => ['lt_3d'], 'q_situation' => ['none']];

        $this->assertSame('see_doctor_24_48h', $this->levelOf('fever-adult', $this->demo(70 * 12), $answers, auto: false));
    }

    // ------------------------------------------------- urinary and wounds

    public function test_urinary_fever_or_loin_pain_is_same_day_at_any_age(): void
    {
        foreach (['fever', 'loin_pain', 'vomiting'] as $sign) {
            $answers = ['q_symptoms' => ['burning', $sign], 'q_context' => ['none']];
            $this->assertSame('o_urgent_1', $this->outcomeOf('urinary-symptoms', $this->demo(30 * 12), $answers), $sign);
            $this->assertSame('o_urgent_1', $this->outcomeOf('urinary-symptoms', $this->demo(30 * 12, 'male', 'not_asked'), $answers), "male {$sign}");
        }
    }

    public function test_pharmacy_for_cystitis_only_for_women_16_plus_without_risk_conditions(): void
    {
        $answers = ['q_symptoms' => ['burning'], 'q_context' => ['none']];

        $this->assertSame('o_pharm_1', $this->outcomeOf('urinary-symptoms', $this->demo(30 * 12), $answers));
        $this->assertSame('o_doc48_risk', $this->outcomeOf('urinary-symptoms', $this->demo(14 * 12), $answers));
        $this->assertSame('o_doc48_risk', $this->outcomeOf('urinary-symptoms', $this->demo(30 * 12, 'unspecified'), $answers));
        $this->assertSame('o_doc48_risk', $this->outcomeOf('urinary-symptoms', $this->demo(30 * 12, conditions: ['diabetes']), $answers));
        $this->assertSame('o_urgent_preg_1', $this->outcomeOf('urinary-symptoms', $this->demo(30 * 12, 'female', 'pregnant'), ['q_symptoms' => ['cloudy'], 'q_context' => ['none']]));
    }

    public function test_an_infected_wound_is_same_day_and_with_fever_or_shivers_the_emergency_department_now(): void
    {
        $answers = ['i_pressure' => ['seen'], 'q_kind' => ['cut'], 'q_features' => ['infected'], 'q_tetanus' => ['lt_10y']];
        $this->assertSame('o_urgent_infected', $this->outcomeOf('wounds-bleeding', $this->demo(40 * 12), $answers));

        $answers['q_features'] = ['infected', 'infected_unwell'];
        $this->assertSame('emergency_department_now', $this->levelOf('wounds-bleeding', $this->demo(40 * 12), $answers, auto: false));
    }

    public function test_a_dirty_wound_with_uncertain_tetanus_is_same_day(): void
    {
        $answers = ['i_pressure' => ['seen'], 'q_kind' => ['puncture'], 'q_features' => ['dirty'], 'q_tetanus' => ['dont_know']];

        $this->assertSame('o_urgent_tetanus', $this->outcomeOf('wounds-bleeding', $this->demo(40 * 12), $answers));
    }

    public function test_nosebleed_follows_the_nhs_emergency_department_criteria(): void
    {
        $demo = $this->demo(40 * 12);
        $nose = ['i_pressure' => ['seen'], 'q_kind' => ['nosebleed']];

        $this->assertSame('o_ed_nose', $this->outcomeOf('wounds-bleeding', $demo, $nose + ['q_nose_duration' => ['ge_15'], 'q_nose_extra' => ['none']]));
        $this->assertSame('o_ed_nose', $this->outcomeOf('wounds-bleeding', $demo, $nose + ['q_nose_duration' => ['lt_15'], 'q_nose_extra' => ['swallow_blood']]));
        $this->assertSame('o_ed_nose_injury', $this->outcomeOf('wounds-bleeding', $demo, $nose + ['q_nose_duration' => ['lt_15'], 'q_nose_extra' => ['injury']]));
        $this->assertSame('o_urgent_nose_infant', $this->outcomeOf('wounds-bleeding', $this->demo(8, 'male', 'not_asked'), $nose + ['q_nose_duration' => ['lt_15'], 'q_nose_extra' => ['none']]));
        $this->assertSame('o_self_nose', $this->outcomeOf('wounds-bleeding', $demo, $nose + ['q_nose_duration' => ['lt_15'], 'q_nose_extra' => ['none']]));
    }

    // ---------------------------------------------------------------- skin

    public function test_rash_skin_screens_for_necrotising_fasciitis_and_non_blanching_spots(): void
    {
        $this->assertSame('o_emergency', $this->redFlag('rash-skin', 'severe_pain_dark_skin')['outcome']);
        $codes = array_column($this->flow('rash-skin')['red_flags'], 'code');
        $this->assertNotContains('nonblanch_unwell', $codes, 'the global red flag covers it regardless of fever');

        $answers = ['q_type' => ['non_blanching'], 'q_fever' => ['no'], 'q_new_drug' => ['no']];
        $this->assertSame('global:emergency_now', $this->outcomeOf('rash-skin', $this->demo(30 * 12), $answers));
    }

    public function test_spreading_red_skin_is_one_level_higher(): void
    {
        $demo = $this->demo(30 * 12);

        $this->assertSame('o_ed_cellulitis', $this->outcomeOf('rash-skin', $demo, ['q_type' => ['red_hot_spreading'], 'q_fever' => ['yes'], 'q_new_drug' => ['no']]));
        $this->assertSame('o_urgent_cellulitis', $this->outcomeOf('rash-skin', $demo, ['q_type' => ['red_hot_spreading'], 'q_fever' => ['no'], 'q_new_drug' => ['no']]));
        $this->assertSame('o_urgent_shingles', $this->outcomeOf('rash-skin', $demo, ['q_type' => ['band_blisters'], 'q_fever' => ['no'], 'q_new_drug' => ['no']]));
        $this->assertSame('o_urgent_drug', $this->outcomeOf('rash-skin', $demo, ['q_type' => ['other'], 'q_fever' => ['yes'], 'q_new_drug' => ['yes']]));
    }

    public function test_earache_with_very_high_temperature_is_same_day(): void
    {
        $this->assertSame('o_urgent_1', $this->outcomeOf('ear-pain-adult', $this->demo(30 * 12), ['q_features' => ['high_fever'], 'q_duration' => ['lt_3d']]));
        $this->assertSame('o_pharm_1', $this->outcomeOf('ear-pain-adult', $this->demo(30 * 12), ['q_features' => ['fever'], 'q_duration' => ['lt_3d']]));
    }
}
