<?php

namespace Tests\Feature\Triage\Concerns;

use App\Services\Triage\V2\AnswerNormalizer;
use App\Services\Triage\V2\FlowWalker;

/**
 * Walks the shipped flow files (database/data/triage/flows) the way the
 * engine does, for tests that pin a routing decision.
 */
trait WalksFlows
{
    /** @return array<string, mixed> */
    protected function flow(string $key): array
    {
        return json_decode((string) file_get_contents(database_path("data/triage/flows/{$key}.json")), true);
    }

    /** @return array<string, mixed> */
    protected function globalCore(): array
    {
        return json_decode((string) file_get_contents(database_path('data/triage/global/core.json')), true);
    }

    /**
     * @param  list<string>  $conditions
     * @return array<string, mixed>
     */
    protected function demo(int $months, string $sex = 'female', string $pregnancy = 'not_pregnant', array $conditions = []): array
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
            'conditions' => $conditions,
        ];
    }

    /**
     * The outcome reference a walk with exactly these answers ends in
     * („o_…“ of the flow, or „global:…“).
     *
     * @param  array<string, mixed>  $demo
     * @param  array<string, list<string>>  $answers
     */
    protected function outcomeOf(string $flow, array $demo, array $answers): string
    {
        $result = (new FlowWalker)->walk($this->flow($flow), $demo, $answers);
        $this->assertTrue($result->finished(), "{$flow}: the walk did not reach an outcome (stopped at {$result->current})");

        return (string) $result->outcome;
    }

    /**
     * The level of the outcome a walk ends in; a global outcome counts as
     * emergency_now (core.json defines only emergency_now and crisis).
     *
     * @param  array<string, mixed>  $demo
     * @param  array<string, list<string>>  $answers
     */
    protected function levelOf(string $flow, array $demo, array $answers, bool $auto = true): string
    {
        $outcome = $auto ? $this->autoOutcome($flow, $demo, $answers) : $this->outcomeOf($flow, $demo, $answers);

        if (str_starts_with($outcome, 'global:')) {
            return $this->globalCore()['outcomes'][substr($outcome, 7)]['level'];
        }

        return $this->flow($flow)['outcomes'][$outcome]['level'];
    }

    /**
     * Walks a flow to its outcome, answering every question on the way from
     * $answers or, when not given, with the calmest choice („none“, „no“,
     * the first option, the number's lower bound + 1, or skipped).
     *
     * @param  array<string, mixed>  $demo
     * @param  array<string, list<string>>  $answers
     */
    protected function autoOutcome(string $flow, array $demo, array $answers): string
    {
        $definition = $this->flow($flow);
        $walker = new FlowWalker;

        for ($i = 0; $i < 40; $i++) {
            $result = $walker->walk($definition, $demo, $answers);
            if ($result->finished()) {
                return (string) $result->outcome;
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

    /** @return array<string, mixed> A red flag of the flow by code. */
    protected function redFlag(string $flow, string $code): array
    {
        foreach ($this->flow($flow)['red_flags'] as $flag) {
            if ($flag['code'] === $code) {
                return $flag;
            }
        }

        $this->fail("{$flow}: no red flag {$code}");
    }
}
