<?php

namespace App\Services\Triage\V2;

use Illuminate\Validation\ValidationException;

/**
 * Checks one answer against its node and returns it in stored form. Only
 * option codes, yes/no/unsure, bounded numbers or "unknown" ever reach the
 * database — never free text (docs/triage-safety.md).
 */
final class AnswerNormalizer
{
    public const INFO_SEEN = 'seen';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $node
     * @param  array<mixed>  $values  as sent (a JSON object would arrive keyed)
     * @return list<string>
     */
    public function normalize(array $node, array $values): array
    {
        $values = array_values($values);

        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                $this->fail();
            }
        }

        $values = array_map(fn ($v) => (string) $v, $values);
        $optional = (bool) ($node['optional'] ?? false);

        if (($node['type'] ?? null) === 'info') {
            return $values === [self::INFO_SEEN] ? $values : $this->fail();
        }

        if ($values === []) {
            return $optional ? [] : $this->fail();
        }

        return match ($node['kind'] ?? null) {
            'single' => count($values) === 1 && in_array($values[0], $this->optionValues($node), true)
                ? $values
                : $this->fail(),
            'multi' => $this->multi($node, $values),
            'yes_no' => count($values) === 1 && in_array($values[0], ($node['allow_unsure'] ?? false) ? ['yes', 'no', 'unsure'] : ['yes', 'no'], true)
                ? $values
                : $this->fail(),
            'number' => [$this->number($node, $values)],
            'scale' => count($values) === 1 && preg_match('/^(10|[0-9])$/', $values[0]) === 1 ? $values : $this->fail(),
            default => $this->fail(),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $values
     * @return list<string>
     */
    private function multi(array $node, array $values): array
    {
        $allowed = $this->optionValues($node);

        if (count($values) !== count(array_unique($values))) {
            $this->fail();
        }

        foreach ($values as $value) {
            if (! in_array($value, $allowed, true)) {
                $this->fail();
            }
        }

        if (count($values) > 1) {
            foreach ($node['options'] as $option) {
                if (($option['exclusive'] ?? false) && in_array($option['value'], $values, true)) {
                    $this->fail();
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $values
     */
    private function number(array $node, array $values): string
    {
        if (count($values) !== 1) {
            $this->fail();
        }

        $value = $values[0];

        if ($value === self::UNKNOWN) {
            return ($node['allow_unknown'] ?? false) ? $value : $this->fail();
        }

        if (preg_match('/^-?\d{1,6}(\.\d{1,3})?$/', $value) !== 1) {
            $this->fail();
        }

        $number = (float) $value;

        if ($number < (float) ($node['min'] ?? 0) || $number > (float) ($node['max'] ?? 0)) {
            $this->fail();
        }

        // "38.50" and "38.5" are the same answer; store one spelling.
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function optionValues(array $node): array
    {
        return array_map(fn (array $o) => (string) $o['value'], $node['options'] ?? []);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['values' => [__('api.guidance.invalid_option')]]);
    }
}
