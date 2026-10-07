<?php

namespace App\Services\Triage\V2\Escalation;

/** Everything an escalation may see: structured and anonymous, never free text. */
final class EscalationRequest
{
    /**
     * @param  array<string, mixed>  $demographics  Demographics::toConditionValues()
     * @param  list<string>  $availableFlows  published flow keys
     */
    public function __construct(
        public readonly array $demographics,
        public readonly ?string $bodyArea,
        public readonly array $availableFlows,
    ) {}
}
