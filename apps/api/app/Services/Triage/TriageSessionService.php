<?php

namespace App\Services\Triage;

use App\Models\TriageFlow;
use App\Models\TriageSession;
use App\Models\TriageSessionAnswer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TriageSessionService
{
    public const RED_FLAGS_STEP_KEY = 'red_flags';

    public const EMERGENCY_OUTCOME_CODE = 'emergency';

    public function __construct(
        private readonly TriageRuleEvaluator $ruleEvaluator,
    ) {}

    public function publishedFlow(): TriageFlow
    {
        $flow = TriageFlow::publishedFlow();

        if ($flow === null) {
            throw ValidationException::withMessages([
                'flow' => ['No symptom guidance flow is published.'],
            ]);
        }

        return $flow->load(['steps.options', 'redFlags', 'outcomes', 'rules']);
    }

    public function startSession(TriageFlow $flow, bool $acceptedTerms, ?User $user): TriageSession
    {
        if (! $acceptedTerms) {
            throw ValidationException::withMessages([
                'accepted_terms' => ['You must accept the guidance terms before continuing.'],
            ]);
        }

        return TriageSession::query()->create([
            'triage_flow_id' => $flow->id,
            'user_id' => $user?->id,
            'terms_accepted_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<int, array{step_key: string, values: list<string>}>  $answers
     */
    public function storeAnswers(TriageSession $session, TriageFlow $flow, array $answers): TriageSession
    {
        $this->ensureSessionOpen($session, $flow);

        foreach ($answers as $answer) {
            $this->upsertAnswer($session, $flow, $answer['step_key'], $answer['values']);
        }

        $session->refresh();

        if ($this->hasRedFlagSelection($session, $flow)) {
            $session->update(['emergency_stopped' => true]);
        }

        return $session->fresh(['answers']);
    }

    public function markEmergency(TriageSession $session, TriageFlow $flow): TriageSession
    {
        $this->ensureSessionOpen($session, $flow);

        $session->update(['emergency_stopped' => true]);

        return $session->fresh();
    }

    public function complete(TriageSession $session, TriageFlow $flow): array
    {
        if ($session->isCompleted()) {
            return $this->outcomePayload($flow, $session->outcome_code ?? self::EMERGENCY_OUTCOME_CODE);
        }

        $this->ensureSessionOpen($session, $flow);

        $session->load('answers');

        if ($session->emergency_stopped || $this->hasRedFlagSelection($session, $flow)) {
            $outcomeCode = self::EMERGENCY_OUTCOME_CODE;
            $session->update([
                'emergency_stopped' => true,
                'outcome_code' => $outcomeCode,
                'completed_at' => Carbon::now(),
            ]);

            return $this->outcomePayload($flow, $outcomeCode);
        }

        $this->assertRequiredStepsAnswered($session, $flow);

        $outcomeCode = $this->ruleEvaluator->evaluate($flow, $session->answersMap());

        $session->update([
            'outcome_code' => $outcomeCode,
            'completed_at' => Carbon::now(),
        ]);

        return $this->outcomePayload($flow, $outcomeCode);
    }

    private function ensureSessionOpen(TriageSession $session, TriageFlow $flow): void
    {
        if ($session->triage_flow_id !== $flow->id) {
            throw ValidationException::withMessages([
                'session' => ['Session does not belong to the current flow.'],
            ]);
        }

        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'session' => ['This guidance session is already complete.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function upsertAnswer(
        TriageSession $session,
        TriageFlow $flow,
        string $stepKey,
        array $values,
    ): void {
        if ($stepKey === self::RED_FLAGS_STEP_KEY) {
            $this->validateRedFlags($flow, $values);

            TriageSessionAnswer::query()->updateOrCreate(
                ['triage_session_id' => $session->id, 'step_key' => $stepKey],
                ['values' => $values],
            );

            return;
        }

        $step = $flow->steps->firstWhere('step_key', $stepKey);

        if ($step === null) {
            throw ValidationException::withMessages([
                "answers.{$stepKey}" => ['Unknown step.'],
            ]);
        }

        if (! $step->allowsValues($values)) {
            throw ValidationException::withMessages([
                "answers.{$stepKey}" => ['Invalid option selected.'],
            ]);
        }

        TriageSessionAnswer::query()->updateOrCreate(
            ['triage_session_id' => $session->id, 'step_key' => $stepKey],
            ['values' => array_values($values)],
        );
    }

    /**
     * @param  list<string>  $values
     */
    private function validateRedFlags(TriageFlow $flow, array $values): void
    {
        $allowed = $flow->redFlags->pluck('code')->all();

        foreach ($values as $value) {
            if (! in_array($value, $allowed, true)) {
                throw ValidationException::withMessages([
                    'answers.red_flags' => ['Invalid red-flag code.'],
                ]);
            }
        }
    }

    private function hasRedFlagSelection(TriageSession $session, TriageFlow $flow): bool
    {
        $answer = $session->answers->firstWhere('step_key', self::RED_FLAGS_STEP_KEY);

        if ($answer === null || $answer->values === []) {
            return false;
        }

        return true;
    }

    private function assertRequiredStepsAnswered(TriageSession $session, TriageFlow $flow): void
    {
        $answeredKeys = $session->answers->pluck('step_key')->all();

        foreach ($flow->steps as $step) {
            if (! $step->is_required) {
                continue;
            }

            if (! in_array($step->step_key, $answeredKeys, true)) {
                throw ValidationException::withMessages([
                    'session' => ["Please answer: {$step->label}"],
                ]);
            }
        }
    }

    private function outcomePayload(TriageFlow $flow, string $outcomeCode): array
    {
        $outcome = $flow->outcomes->firstWhere('code', $outcomeCode)
            ?? $flow->outcomes->firstWhere('code', self::EMERGENCY_OUTCOME_CODE);

        if ($outcome === null) {
            return [
                'outcome_code' => $outcomeCode,
                'title' => 'General information',
                'body' => 'We could not load detailed guidance. If you are worried about your health, contact a healthcare professional or emergency services (194 / 112).',
                'handoffs' => [
                    ['type' => 'home', 'label' => 'Home', 'href' => '/'],
                    ['type' => 'emergency', 'label' => 'Emergency numbers', 'href' => null],
                ],
            ];
        }

        return [
            'outcome_code' => $outcome->code,
            'title' => $outcome->title,
            'body' => $outcome->body,
            'handoffs' => $outcome->handoffs ?? [],
        ];
    }
}
