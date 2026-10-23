<?php

namespace Tests\Feature\Triage\SafetyFixes;

use Tests\Feature\Triage\Concerns\WalksFlows;
use Tests\TestCase;

/**
 * Safety fixes of 2026-10-23 (CHANGES-2026-10-23 in the clinical review
 * pack), pending clinician review: suicidal thoughts and suspected
 * postpartum psychosis reach the crisis outcome.
 */
class MentalHealthCrisisTest extends TestCase
{
    use WalksFlows;

    public function test_any_thoughts_of_death_or_self_harm_reach_the_crisis_outcome(): void
    {
        foreach ([18 * 12, 40 * 12, 70 * 12] as $months) {
            foreach (['sometimes', 'often'] as $answer) {
                $this->assertSame('global:crisis', $this->outcomeOf('mental-health', $this->demo($months), ['i_you_matter' => ['seen'], 'q_thoughts' => [$answer]]), "{$months} months, {$answer}");
            }
        }
    }

    public function test_no_such_thoughts_continue_the_questions(): void
    {
        $this->assertNotSame('global:crisis', $this->autoOutcome('mental-health', $this->demo(30 * 12), ['q_thoughts' => ['no']]));
    }

    public function test_suspected_postpartum_psychosis_reaches_the_crisis_outcome(): void
    {
        foreach (['unusual', 'elated'] as $sign) {
            $answers = ['q_thoughts' => ['no'], 'q_main' => [$sign], 'q_duration' => ['lt_2w'], 'q_function' => ['little']];
            $this->assertSame('global:crisis', $this->autoOutcome('mental-health', $this->demo(30 * 12, 'female', 'postpartum'), $answers), $sign);
        }
    }

    public function test_the_crisis_outcome_lists_support_lines_after_194_and_112(): void
    {
        $numbers = array_column($this->globalCore()['outcomes']['crisis']['call'], 'number');

        $this->assertSame(['194', '112', '+389 72 271 048', '+389 70 390 632', '15 315'], $numbers);
    }
}
