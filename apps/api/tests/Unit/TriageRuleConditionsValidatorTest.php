<?php

namespace Tests\Unit;

use App\Support\Triage\TriageRuleConditionsValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TriageRuleConditionsValidatorTest extends TestCase
{
    #[Test]
    public function test_accepts_single_condition(): void
    {
        $input = [
            'step' => 'severity',
            'operator' => 'in',
            'values' => ['severe'],
        ];

        $this->assertSame($input, TriageRuleConditionsValidator::validate($input));
    }

    #[Test]
    public function test_accepts_all_group(): void
    {
        $input = [
            'all' => [
                [
                    'step' => 'duration',
                    'operator' => 'in',
                    'values' => ['over_week'],
                ],
                [
                    'step' => 'severity',
                    'operator' => 'eq',
                    'values' => ['moderate'],
                ],
            ],
        ];

        $this->assertSame($input, TriageRuleConditionsValidator::validate($input));
    }

    #[Test]
    public function test_rejects_missing_step(): void
    {
        $this->expectException(ValidationException::class);

        TriageRuleConditionsValidator::validate([
            'operator' => 'in',
            'values' => ['severe'],
        ]);
    }

    #[Test]
    public function test_rejects_invalid_operator(): void
    {
        $this->expectException(ValidationException::class);

        TriageRuleConditionsValidator::validate([
            'step' => 'severity',
            'operator' => 'contains',
            'values' => ['severe'],
        ]);
    }

    #[Test]
    public function test_rejects_empty_values(): void
    {
        $this->expectException(ValidationException::class);

        TriageRuleConditionsValidator::validate([
            'step' => 'severity',
            'operator' => 'in',
            'values' => [],
        ]);
    }
}
