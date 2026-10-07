<?php

namespace App\Support\Verification\Engine;

/**
 * What the rules decided for one profile: verified under a rule, or not,
 * with the reason. Evidence is internal (ids and rule names only: never a
 * licence number or a ФЗО facsimile).
 */
final readonly class Verdict
{
    /**
     * @param  list<array<string, scalar|null>>  $evidence
     */
    private function __construct(
        public ?VerificationRule $rule,
        public ?string $reason,
        public array $evidence,
    ) {}

    /**
     * @param  array<string, scalar|null>  $evidence
     */
    public static function verified(VerificationRule $rule, array $evidence): self
    {
        return new self($rule, null, [['rule' => $rule->value] + array_filter($evidence, fn ($value): bool => $value !== null)]);
    }

    /**
     * @param  array<string, scalar|null>  $evidence
     */
    public static function unverified(string $reason, array $evidence = []): self
    {
        $evidence = array_filter($evidence, fn ($value): bool => $value !== null);

        return new self(null, $reason, $evidence === [] ? [] : [$evidence]);
    }

    public function isVerified(): bool
    {
        return $this->rule !== null;
    }
}
