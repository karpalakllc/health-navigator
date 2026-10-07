<?php

namespace App\Services\Triage\V2;

use Illuminate\Validation\ValidationException;

/**
 * Who the guidance is for (docs/triage-flows.md §4). Asked once per session,
 * before the symptoms; flows read it through `{"demo": ...}` conditions.
 *
 * Stored as anonymous structured answers (`demo.*` rows) like any other
 * answer, purged with the session.
 */
final class Demographics
{
    public const AGE_BANDS = [
        'infant_0_3m',
        'infant_3_12m',
        'child_1_4',
        'child_5_12',
        'teen_13_17',
        'adult_18_64',
        'older_65_plus',
    ];

    public const SEXES = ['female', 'male', 'unspecified'];

    public const PREGNANCY = ['pregnant', 'postpartum', 'not_pregnant', 'unsure', 'not_asked'];

    public const CONDITIONS = [
        'immunosuppressed',
        'diabetes',
        'heart_disease',
        'lung_disease',
        'kidney_disease',
        'pregnancy_complication_history',
    ];

    public const FIELDS = ['age_band', 'age_months', 'age_years', 'sex', 'pregnancy', 'conditions'];

    /** Oldest age accepted, in years. */
    public const MAX_AGE_YEARS = 120;

    /**
     * @param  list<string>  $conditions
     */
    public function __construct(
        public readonly int $ageMonths,
        public readonly string $sex,
        public readonly string $pregnancy,
        public readonly array $conditions,
    ) {}

    /**
     * @param  array{age_value?: mixed, age_unit?: mixed, sex?: mixed, pregnancy?: mixed, conditions?: mixed}  $input
     */
    public static function fromInput(array $input): self
    {
        $unit = $input['age_unit'] ?? null;
        $value = $input['age_value'] ?? null;

        if (! in_array($unit, ['years', 'months', 'weeks'], true) || ! is_numeric($value)) {
            throw ValidationException::withMessages(['age_value' => [__('api.guidance.v2.invalid_age')]]);
        }

        $months = match ($unit) {
            'years' => (int) floor((float) $value * 12),
            'months' => (int) floor((float) $value),
            'weeks' => (int) floor((float) $value * 7 / 30.4375),
        };

        if ((float) $value < 0 || $months > self::MAX_AGE_YEARS * 12) {
            throw ValidationException::withMessages(['age_value' => [__('api.guidance.v2.invalid_age')]]);
        }

        $sex = $input['sex'] ?? null;

        if (! in_array($sex, self::SEXES, true)) {
            throw ValidationException::withMessages(['sex' => [__('api.guidance.v2.invalid_choice')]]);
        }

        $pregnancy = $input['pregnancy'] ?? null;

        if (! self::pregnancyIsAsked($sex, intdiv($months, 12))) {
            $pregnancy = 'not_asked';
        } elseif (! in_array($pregnancy, ['pregnant', 'postpartum', 'not_pregnant', 'unsure'], true)) {
            throw ValidationException::withMessages(['pregnancy' => [__('api.guidance.v2.invalid_choice')]]);
        }

        $conditions = $input['conditions'] ?? [];

        if (! is_array($conditions)) {
            throw ValidationException::withMessages(['conditions' => [__('api.guidance.v2.invalid_choice')]]);
        }

        foreach ($conditions as $condition) {
            if (! in_array($condition, self::CONDITIONS, true)) {
                throw ValidationException::withMessages(['conditions' => [__('api.guidance.v2.invalid_choice')]]);
            }
        }

        return new self($months, $sex, $pregnancy, array_values(array_unique($conditions)));
    }

    public static function pregnancyIsAsked(string $sex, int $ageYears): bool
    {
        return $sex !== 'male' && $ageYears >= 10 && $ageYears <= 55;
    }

    public function ageYears(): int
    {
        return intdiv($this->ageMonths, 12);
    }

    public function ageBand(): string
    {
        $months = $this->ageMonths;
        $years = $this->ageYears();

        return match (true) {
            $months < 3 => 'infant_0_3m',
            $months < 12 => 'infant_3_12m',
            $years <= 4 => 'child_1_4',
            $years <= 12 => 'child_5_12',
            $years <= 17 => 'teen_13_17',
            $years <= 64 => 'adult_18_64',
            default => 'older_65_plus',
        };
    }

    /**
     * The values conditions compare against.
     *
     * @return array{age_band: string, age_months: int, age_years: int, sex: string, pregnancy: string, conditions: list<string>}
     */
    public function toConditionValues(): array
    {
        return [
            'age_band' => $this->ageBand(),
            'age_months' => $this->ageMonths,
            'age_years' => $this->ageYears(),
            'sex' => $this->sex,
            'pregnancy' => $this->pregnancy,
            'conditions' => $this->conditions,
        ];
    }

    /**
     * As stored in triage_session_answers (step_key => values). The age is
     * kept in whole months only.
     *
     * @return array<string, list<string>>
     */
    public function toAnswerRows(): array
    {
        return [
            'demo.age_months' => [(string) $this->ageMonths],
            'demo.sex' => [$this->sex],
            'demo.pregnancy' => [$this->pregnancy],
            'demo.conditions' => $this->conditions,
        ];
    }

    /**
     * @param  array<string, list<string>>  $answers  the session's answers map
     */
    public static function fromAnswerRows(array $answers): ?self
    {
        if (! isset($answers['demo.age_months'][0], $answers['demo.sex'][0], $answers['demo.pregnancy'][0])) {
            return null;
        }

        return new self(
            (int) $answers['demo.age_months'][0],
            $answers['demo.sex'][0],
            $answers['demo.pregnancy'][0],
            $answers['demo.conditions'] ?? [],
        );
    }
}
