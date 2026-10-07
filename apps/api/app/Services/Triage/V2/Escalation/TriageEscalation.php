<?php

namespace App\Services\Triage\V2\Escalation;

/**
 * The seam for a future AI layer (3f-b, docs/triage-safety.md). Rule-based
 * guidance never depends on it: whatever an implementation answers is only a
 * suggestion among published, clinician-reviewed flows, and the engine still
 * runs those flows' red flags and rules.
 *
 * Called from GuidanceSessionService::noMatch() — the visitor found no
 * matching symptom. Implementations must not receive free text or anything
 * identifying; EscalationRequest carries only structured, anonymous fields.
 */
interface TriageEscalation
{
    /** False while the feature flag (config triage.escalation.enabled) is off. */
    public function enabled(): bool;

    /**
     * Flow keys worth offering, most relevant first. Keys that are not in
     * $request->availableFlows are ignored by the caller.
     *
     * @return list<string>
     */
    public function suggestFlows(EscalationRequest $request): array;
}
