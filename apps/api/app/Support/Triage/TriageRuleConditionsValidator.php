<?php

namespace App\Support\Triage;

use Illuminate\Validation\ValidationException;

final class TriageRuleConditionsValidator
{
    private const ALLOWED_OPERATORS = ['in', 'eq', 'includes_any'];

    /**
     * @return array<string, mixed>
     */
    public static function validate(mixed $conditions): array
    {
        if (! is_array($conditions)) {
            throw ValidationException::withMessages([
                'conditions' => 'Conditions must be a JSON object.',
            ]);
        }

        if (isset($conditions['all'])) {
            if (! is_array($conditions['all']) || $conditions['all'] === []) {
                throw ValidationException::withMessages([
                    'conditions' => 'The "all" array must contain at least one condition.',
                ]);
            }

            foreach ($conditions['all'] as $index => $child) {
                self::validateLeaf($child, "all.{$index}");
            }

            return $conditions;
        }

        self::validateLeaf($conditions);

        return $conditions;
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private static function validateLeaf(array $condition, string $path = 'conditions'): void
    {
        $step = $condition['step'] ?? null;

        if (! is_string($step) || $step === '') {
            throw ValidationException::withMessages([
                $path => 'Each condition needs a non-empty "step" key (step_key from the flow).',
            ]);
        }

        $operator = $condition['operator'] ?? 'in';

        if (! is_string($operator) || ! in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw ValidationException::withMessages([
                $path => 'Operator must be one of: '.implode(', ', self::ALLOWED_OPERATORS).'.',
            ]);
        }

        $values = $condition['values'] ?? $condition['in'] ?? null;

        if (! is_array($values) || $values === []) {
            throw ValidationException::withMessages([
                $path => 'Provide a non-empty "values" array of option values.',
            ]);
        }

        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                throw ValidationException::withMessages([
                    $path => 'Each value in "values" must be a string.',
                ]);
            }
        }
    }
}
