<?php

namespace App\Services\Triage\V2\Escalation;

/** The default: no AI, no external call, no suggestion. */
final class NullEscalation implements TriageEscalation
{
    public function enabled(): bool
    {
        return false;
    }

    public function suggestFlows(EscalationRequest $request): array
    {
        return [];
    }
}
