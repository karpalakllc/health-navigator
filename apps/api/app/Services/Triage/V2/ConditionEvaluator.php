<?php

namespace App\Services\Triage\V2;

/**
 * Evaluates the condition language of docs/triage-flows.md §7.
 *
 * Fails closed: anything malformed evaluates to false (the linter rejects such
 * flows before they can be imported, so this only guards against a bug).
 * A comparison on an unanswered question, or a numeric comparison on
 * „unknown“, is false.
 */
final class ConditionEvaluator
{
    public const OPERATORS = ['eq', 'ne', 'in', 'includes', 'includes_any', 'includes_all', 'gte', 'gt', 'lte', 'lt', 'between'];

    public const NUMERIC_OPERATORS = ['gte', 'gt', 'lte', 'lt', 'between'];

    /**
     * @param  array<string, mixed>  $demo  Demographics::toConditionValues()
     * @param  array<string, list<string>>  $answers  node id => values (this flow, on the path)
     * @param  array<string, int|float>  $scores
     */
    public function __construct(
        private readonly array $demo,
        private readonly array $answers,
        private readonly array $scores = [],
    ) {}

    public function withScores(array $scores): self
    {
        return new self($this->demo, $this->answers, $scores);
    }

    public function evaluate(mixed $condition): bool
    {
        if (! is_array($condition) || $condition === []) {
            return false;
        }

        if (array_key_exists('all', $condition)) {
            return is_array($condition['all']) && $condition['all'] !== []
                && array_reduce($condition['all'], fn (bool $carry, $c) => $carry && $this->evaluate($c), true);
        }

        if (array_key_exists('any', $condition)) {
            return is_array($condition['any'])
                && array_reduce($condition['any'], fn (bool $carry, $c) => $carry || $this->evaluate($c), false);
        }

        if (array_key_exists('not', $condition)) {
            return is_array($condition['not']) && ! $this->evaluate($condition['not']);
        }

        if (isset($condition['answer']) && is_string($condition['answer'])) {
            $values = $this->answers[$condition['answer']] ?? null;

            if (array_key_exists('answered', $condition)) {
                return ($values !== null && $values !== []) === (bool) $condition['answered'];
            }

            if ($values === null) {
                return false;
            }

            return $this->compare($values, $condition);
        }

        if (isset($condition['demo']) && is_string($condition['demo'])) {
            if (! array_key_exists($condition['demo'], $this->demo)) {
                return false;
            }

            $value = $this->demo[$condition['demo']];

            return $this->compare(is_array($value) ? array_values($value) : [(string) $value], $condition);
        }

        if (isset($condition['score']) && is_string($condition['score'])) {
            if (! array_key_exists($condition['score'], $this->scores)) {
                return false;
            }

            return $this->compare([(string) $this->scores[$condition['score']]], $condition);
        }

        return false;
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, mixed>  $condition
     */
    private function compare(array $values, array $condition): bool
    {
        $operator = null;

        foreach (self::OPERATORS as $candidate) {
            if (array_key_exists($candidate, $condition)) {
                $operator = $candidate;
                break;
            }
        }

        if ($operator === null) {
            return false;
        }

        $expected = $condition[$operator];
        $single = count($values) === 1 ? $values[0] : null;

        return match ($operator) {
            'eq' => $single !== null && $single === self::str($expected),
            'ne' => $single !== null && $single !== self::str($expected),
            'in' => $single !== null && is_array($expected) && in_array($single, array_map(self::str(...), $expected), true),
            'includes' => in_array(self::str($expected), $values, true),
            'includes_any' => is_array($expected) && array_intersect($values, array_map(self::str(...), $expected)) !== [],
            'includes_all' => is_array($expected) && $expected !== [] && array_diff(array_map(self::str(...), $expected), $values) === [],
            'gte', 'gt', 'lte', 'lt' => $this->numeric($single, $expected, $operator),
            'between' => is_array($expected) && count($expected) === 2
                && $this->numeric($single, $expected[0], 'gte') && $this->numeric($single, $expected[1], 'lte'),
        };
    }

    private function numeric(?string $actual, mixed $expected, string $operator): bool
    {
        if ($actual === null || ! is_numeric($actual) || ! is_numeric($expected)) {
            return false;
        }

        $a = (float) $actual;
        $e = (float) $expected;

        return match ($operator) {
            'gte' => $a >= $e,
            'gt' => $a > $e,
            'lte' => $a <= $e,
            'lt' => $a < $e,
            default => false,
        };
    }

    private static function str(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
