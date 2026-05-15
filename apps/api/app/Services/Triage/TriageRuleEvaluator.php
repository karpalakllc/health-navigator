<?php

namespace App\Services\Triage;

use App\Models\TriageFlow;
use App\Models\TriageRule;

class TriageRuleEvaluator
{
    public const DEFAULT_OUTCOME_CODE = 'general_information';

    public function evaluate(TriageFlow $flow, array $answersMap): string
    {
        $rules = $flow->rules()->orderBy('priority')->get();

        foreach ($rules as $rule) {
            if ($this->matches($rule, $answersMap)) {
                return $rule->outcome_code;
            }
        }

        return self::DEFAULT_OUTCOME_CODE;
    }

    /**
     * @param  array<string, list<string>>  $answersMap
     */
    private function matches(TriageRule $rule, array $answersMap): bool
    {
        $conditions = $rule->conditions;

        if (isset($conditions['all']) && is_array($conditions['all'])) {
            foreach ($conditions['all'] as $condition) {
                if (! $this->matchesCondition($condition, $answersMap)) {
                    return false;
                }
            }

            return true;
        }

        return $this->matchesCondition($conditions, $answersMap);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, list<string>>  $answersMap
     */
    private function matchesCondition(array $condition, array $answersMap): bool
    {
        $step = $condition['step'] ?? null;

        if (! is_string($step) || $step === '') {
            return false;
        }

        $selected = $answersMap[$step] ?? [];
        $expected = $condition['values'] ?? $condition['in'] ?? [];

        if (! is_array($expected)) {
            return false;
        }

        $operator = $condition['operator'] ?? 'in';

        return match ($operator) {
            'eq' => count($selected) === 1 && count($expected) === 1 && $selected[0] === $expected[0],
            'includes_any' => array_intersect($selected, $expected) !== [],
            'in' => array_intersect($selected, $expected) !== [],
            default => false,
        };
    }
}
