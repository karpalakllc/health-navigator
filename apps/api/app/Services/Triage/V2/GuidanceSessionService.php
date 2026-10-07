<?php

namespace App\Services\Triage\V2;

use App\Enums\TriageOutcomeLevel;
use App\Models\TriageFlowVersion;
use App\Models\TriageOutcomeStat;
use App\Models\TriageSession;
use App\Models\TriageSessionAnswer;
use App\Models\TriageSessionFlow;
use App\Services\Triage\V2\Escalation\EscalationRequest;
use App\Services\Triage\V2\Escalation\TriageEscalation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs a v2 guidance session (docs/triage-flows.md):
 *
 *   demographics → symptoms (1–3 flows) → combined red-flag screen →
 *   each flow's questions, most urgent flow first → one result.
 *
 * Stages are linear: re-submitting an earlier stage (the visitor went back)
 * discards everything recorded after it. Any red flag, the emergency
 * shortcut or an emergency outcome ends the session with an emergency result
 * that cannot be left without starting a new session.
 *
 * Sessions are anonymous (no account, no IP); answers are option codes and
 * bounded numbers only, purged with the session after 90 days.
 */
final class GuidanceSessionService
{
    public const SCREEN_KEY = 'screen.red_flags';

    public const SHORTCUT_OUTCOME = 'emergency_shortcut';

    public const RED_FLAG_OUTCOME = 'red_flag';

    private ?GlobalScreen $global = null;

    public function __construct(
        private readonly FlowWalker $walker,
        private readonly AnswerNormalizer $normalizer,
        private readonly OutcomePresenter $presenter,
        private readonly TriageEscalation $escalation,
    ) {}

    // ------------------------------------------------------------- catalog

    /**
     * Published flows, without any rule: what the symptom picker needs.
     *
     * @return list<array{key: string, title: string, body_areas: list<string>, search_terms: list<string>, age_bands: list<string>, urgency_rank: int}>
     */
    public function catalog(): array
    {
        return $this->publishedVersions()
            ->map(fn (TriageFlowVersion $v) => [
                'key' => (string) $v->definition['key'],
                'title' => (string) $v->definition['title'],
                'body_areas' => array_values($v->definition['body_areas']),
                'search_terms' => array_values($v->definition['search_terms']),
                'age_bands' => array_values($v->definition['audience']['age_bands']),
                'urgency_rank' => (int) $v->definition['urgency_rank'],
            ])
            ->sortBy('title', SORT_NATURAL)
            ->values()
            ->all();
    }

    /** @return Collection<int, TriageFlowVersion> */
    private function publishedVersions(): Collection
    {
        return TriageFlowVersion::query()
            ->where('status', TriageFlowVersion::STATUS_PUBLISHED)
            ->whereHas('flow', fn ($q) => $q->v2())
            ->with('flow')
            ->get();
    }

    // ------------------------------------------------------------- session

    /**
     * @return array{0: TriageSession, 1: string}
     */
    public function start(bool $acceptedTerms): array
    {
        if (! $acceptedTerms) {
            throw ValidationException::withMessages(['accepted_terms' => [__('api.guidance.terms_required')]]);
        }

        $token = Str::random(64);

        $session = TriageSession::query()->create([
            'engine' => TriageSession::ENGINE_V2,
            'triage_flow_id' => null,
            'token_hash' => TriageSession::hashToken($token),
            'terms_accepted_at' => Carbon::now(),
        ]);

        return [$session, $token];
    }

    /** @param  array<string, mixed>  $input */
    public function saveDemographics(TriageSession $session, array $input): array
    {
        $this->ensureOpen($session);
        $demo = Demographics::fromInput($input);

        DB::transaction(function () use ($session, $demo): void {
            $this->clearAfter($session, 'demographics');

            foreach ($demo->toAnswerRows() as $key => $values) {
                $this->putAnswer($session, $key, $values);
            }
        });

        return $this->state($session->fresh());
    }

    /** @param  list<string>  $keys */
    public function chooseSymptoms(TriageSession $session, array $keys): array
    {
        $this->ensureOpen($session);
        $demo = $this->demographics($session);
        $keys = array_values(array_unique($keys));
        $max = (int) config('triage.max_symptoms', 3);

        if ($keys === []) {
            throw ValidationException::withMessages(['flows' => [__('api.guidance.v2.symptoms_required')]]);
        }

        if (count($keys) > $max) {
            throw ValidationException::withMessages(['flows' => [__('api.guidance.v2.too_many_symptoms', ['max' => $max])]]);
        }

        $published = $this->publishedVersions()->keyBy(fn (TriageFlowVersion $v) => $v->flow->key);
        $chosen = [];

        foreach ($keys as $index => $key) {
            $version = $published->get($key);

            if ($version === null) {
                throw ValidationException::withMessages(['flows' => [__('api.guidance.v2.unknown_flow')]]);
            }

            if (! in_array($demo->ageBand(), $version->definition['audience']['age_bands'], true)) {
                throw ValidationException::withMessages(['flows' => [__('api.guidance.v2.not_for_age')]]);
            }

            $chosen[] = [$version, (int) $version->definition['urgency_rank'], $index];
        }

        // Most urgent symptom first; the visitor's order breaks ties.
        usort($chosen, fn ($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        DB::transaction(function () use ($session, $chosen): void {
            $this->clearAfter($session, 'demographics');

            foreach ($chosen as $position => [$version]) {
                TriageSessionFlow::query()->create([
                    'triage_session_id' => $session->id,
                    'triage_flow_version_id' => $version->id,
                    'flow_key' => $version->flow->key,
                    'position' => $position,
                ]);
            }
        });

        return $this->state($session->fresh());
    }

    /** @param  list<string>  $codes */
    public function answerScreen(TriageSession $session, array $codes): array
    {
        $this->ensureOpen($session);
        $demo = $this->demographics($session);
        $flows = $this->sessionFlows($session);

        if ($flows->isEmpty()) {
            throw ValidationException::withMessages(['flows' => [__('api.guidance.v2.symptoms_required')]]);
        }

        $items = collect($this->screenItems($demo, $flows))->keyBy('code');

        foreach ($codes as $code) {
            if (! $items->has($code)) {
                throw ValidationException::withMessages(['red_flags' => [__('api.guidance.invalid_red_flag')]]);
            }
        }

        DB::transaction(function () use ($session, $codes): void {
            $this->clearAfter($session, 'symptoms');
            $this->putAnswer($session, self::SCREEN_KEY, array_values(array_unique($codes)));
        });

        if ($codes !== []) {
            $outcomeRefs = array_map(fn ($code) => $items->get($code)['outcome'], $codes);

            return $this->finishEmergency($session->fresh(), $outcomeRefs, self::RED_FLAG_OUTCOME);
        }

        return $this->state($session->fresh());
    }

    /**
     * Answer the current question of a flow, or re-answer one asked earlier
     * (the visitor went back): answers after it are discarded.
     *
     * @param  list<mixed>  $values
     */
    public function answer(TriageSession $session, string $flowKey, string $nodeId, array $values): array
    {
        $this->ensureOpen($session);
        $demo = $this->demographics($session);
        $this->ensureScreened($session);
        $flows = $this->sessionFlows($session);
        $flow = $flows->firstWhere('flow_key', $flowKey);

        if ($flow === null) {
            throw ValidationException::withMessages(['flow' => [__('api.guidance.v2.unknown_flow')]]);
        }

        // Earlier flows must be finished (and not in an emergency).
        $answers = $this->answersMap($session);

        foreach ($flows as $earlier) {
            if ($earlier->position >= $flow->position) {
                break;
            }

            if (! $this->walk($earlier, $demo, $answers)->finished()) {
                throw ValidationException::withMessages(['node' => [__('api.guidance.v2.not_current')]]);
            }
        }

        $definition = $flow->version->definition;
        $walk = $this->walk($flow, $demo, $answers);
        $onPath = in_array($nodeId, $walk->path, true);

        if (! $onPath && $walk->current !== $nodeId) {
            throw ValidationException::withMessages(['node' => [__('api.guidance.v2.not_current')]]);
        }

        $node = $definition['nodes'][$nodeId];
        $stored = $this->normalizer->normalize($node, $values);

        DB::transaction(function () use ($session, $flows, $flow, $walk, $nodeId, $stored): void {
            $keep = [];

            foreach ($walk->path as $id) {
                if ($id === $nodeId) {
                    break;
                }

                $keep[] = self::answerKey($flow->flow_key, $id);
            }

            // This flow: drop everything not before the node. Later flows: drop all.
            $later = $flows->where('position', '>', $flow->position)->pluck('flow_key')->all();

            $session->answers()
                ->where(function ($q) use ($flow, $later): void {
                    $q->where('step_key', 'like', self::answerKey($flow->flow_key, '').'%');

                    foreach ($later as $key) {
                        $q->orWhere('step_key', 'like', self::answerKey($key, '').'%');
                    }
                })
                ->whereNotIn('step_key', $keep)
                ->delete();

            $this->putAnswer($session, self::answerKey($flow->flow_key, $nodeId), $stored);
        });

        return $this->state($session->fresh());
    }

    /** The „Потребна ми е итна помош“ shortcut: allowed at any stage of an open session. */
    public function emergencyShortcut(TriageSession $session): array
    {
        $this->ensureOpen($session);

        return $this->finishEmergency($session, ['global:emergency_now'], self::SHORTCUT_OUTCOME);
    }

    /**
     * The visitor found no matching symptom. This is where the AI layer
     * (3f-b) would be asked; with escalation off the NullEscalation answers
     * and the general flow is offered.
     *
     * @return array{suggested: list<string>}
     */
    public function noMatch(TriageSession $session, ?string $bodyArea): array
    {
        $this->ensureOpen($session);
        $demo = $this->demographics($session);
        $available = array_column($this->catalog(), 'key');
        $suggested = [];

        if ($this->escalation->enabled()) {
            try {
                $suggested = $this->escalation->suggestFlows(new EscalationRequest(
                    demographics: $demo->toConditionValues(),
                    bodyArea: in_array($bodyArea, FlowLinter::BODY_AREAS, true) ? $bodyArea : null,
                    availableFlows: $available,
                ));
            } catch (Throwable $e) {
                // The rule-based fallback below always answers.
                Log::warning('Triage escalation failed', ['exception' => $e::class]);
            }
        }

        $suggested = array_values(array_intersect($suggested, $available));
        $fallback = (string) config('triage.fallback_flow');

        if ($suggested === [] && in_array($fallback, $available, true)) {
            $suggested = [$fallback];
        }

        return ['suggested' => $suggested];
    }

    // --------------------------------------------------------------- state

    /**
     * Where the session stands, as the browser needs it. Never contains a
     * rule, a condition, a score or the routing of a node.
     */
    public function state(TriageSession $session): array
    {
        if ($session->isCompleted()) {
            return $this->resultState($session);
        }

        $answers = $this->answersMap($session);
        $demo = Demographics::fromAnswerRows($answers);

        if ($demo === null) {
            return ['session_id' => $session->id, 'stage' => 'demographics'];
        }

        $flows = $this->sessionFlows($session);

        if ($flows->isEmpty()) {
            return ['session_id' => $session->id, 'stage' => 'symptoms', 'age_band' => $demo->ageBand()];
        }

        if (! array_key_exists(self::SCREEN_KEY, $answers)) {
            return [
                'session_id' => $session->id,
                'stage' => 'screen',
                'flows' => $this->flowRefs($flows),
                'screen' => array_map(
                    fn (array $item) => array_diff_key($item, ['outcome' => true]),
                    $this->screenItems($demo, $flows),
                ),
            ];
        }

        $finished = [];

        foreach ($flows as $flow) {
            $walk = $this->walk($flow, $demo, $answers);

            if ($walk->broken) {
                // A definition the linter should have rejected: fail closed.
                Log::error('Triage flow walk broke', ['flow' => $flow->flow_key, 'version' => $flow->triage_flow_version_id]);

                return $this->finishWith($session, [[$flow, 'global:emergency_now']], fallback: true);
            }

            if (! $walk->finished()) {
                return [
                    'session_id' => $session->id,
                    'stage' => 'question',
                    'flows' => $this->flowRefs($flows),
                    'flow' => ['key' => $flow->flow_key, 'title' => $flow->version->definition['title'], 'position' => $flow->position],
                    'node' => $this->publicNode((string) $walk->current, $flow->version->definition['nodes'][$walk->current]),
                    'progress' => [
                        'answered' => count($walk->path),
                        'remaining_max' => $this->longestRemaining($flow->version->definition, (string) $walk->current),
                    ],
                    'path' => array_map(fn ($id) => [
                        'node' => $this->publicNode($id, $flow->version->definition['nodes'][$id]),
                        'values' => $answers[self::answerKey($flow->flow_key, $id)] ?? [],
                    ], $walk->path),
                ];
            }

            $finished[] = [$flow, (string) $walk->outcome];

            if ($this->resolveOutcome($flow, (string) $walk->outcome)['level'] === TriageOutcomeLevel::EmergencyNow->value) {
                // An emergency in any flow stops the remaining flows.
                break;
            }
        }

        return $this->finishWith($session, $finished);
    }

    // ------------------------------------------------------------ finishing

    /**
     * @param  list<string>  $outcomeRefs  "global:<id>" or "<flow key>:<id>"
     */
    private function finishEmergency(TriageSession $session, array $outcomeRefs, string $statOutcome): array
    {
        $flows = $this->sessionFlows($session);
        $outcomes = array_map(fn (string $ref) => $this->resolveRef($flows, $ref), $outcomeRefs);
        // Crisis wording first when it applies; otherwise the first ticked flag.
        usort($outcomes, fn ($a, $b) => (int) (bool) ($b['crisis'] ?? false) <=> (int) (bool) ($a['crisis'] ?? false));
        $primary = $outcomes[0];

        DB::transaction(function () use ($session, $flows, $primary, $statOutcome): void {
            $session->update([
                'emergency_stopped' => true,
                'outcome_code' => $primary['ref'],
                'outcome_level' => TriageOutcomeLevel::EmergencyNow->value,
                'completed_at' => Carbon::now(),
            ]);

            foreach ($flows as $flow) {
                $flow->update([
                    'outcome_id' => $statOutcome,
                    'outcome_level' => TriageOutcomeLevel::EmergencyNow->value,
                    'completed_at' => Carbon::now(),
                ]);
                TriageOutcomeStat::record($flow->flow_key, $statOutcome, TriageOutcomeLevel::EmergencyNow->value);
            }

            if ($flows->isEmpty()) {
                TriageOutcomeStat::record('_none', $statOutcome, TriageOutcomeLevel::EmergencyNow->value);
            }
        });

        return $this->resultState($session->fresh(), array_column($outcomes, 'ref'));
    }

    /**
     * @param  list<array{0: TriageSessionFlow, 1: string}>  $finished  flow and outcome ref reached
     */
    private function finishWith(TriageSession $session, array $finished, bool $fallback = false): array
    {
        DB::transaction(function () use ($session, $finished): void {
            $primary = null;

            foreach ($finished as [$flow, $outcomeId]) {
                $outcome = $this->resolveOutcome($flow, $outcomeId);
                $level = TriageOutcomeLevel::from($outcome['level']);

                $flow->update([
                    'outcome_id' => $outcomeId,
                    'outcome_level' => $level->value,
                    'completed_at' => Carbon::now(),
                ]);
                TriageOutcomeStat::record($flow->flow_key, $outcomeId, $level->value);

                if ($primary === null || $level->isMoreUrgentThan($primary[1])) {
                    $primary = [$outcome['ref'], $level];
                }
            }

            $session->update([
                'emergency_stopped' => $primary[1] === TriageOutcomeLevel::EmergencyNow,
                'outcome_code' => $primary[0],
                'outcome_level' => $primary[1]->value,
                'completed_at' => Carbon::now(),
            ]);
        });

        return $this->resultState($session->fresh(), null, $fallback);
    }

    /**
     * @param  list<string>|null  $emergencyRefs  the red-flag / shortcut outcomes, when that is how it ended
     */
    private function resultState(TriageSession $session, ?array $emergencyRefs = null, bool $fallback = false): array
    {
        $flows = $this->sessionFlows($session);
        $items = [];

        if ($emergencyRefs === null && in_array($flows->first()?->outcome_id, [self::RED_FLAG_OUTCOME, self::SHORTCUT_OUTCOME], true)) {
            $emergencyRefs = [(string) $session->outcome_code];
        }

        if ($emergencyRefs === null && $flows->isEmpty()) {
            $emergencyRefs = [(string) ($session->outcome_code ?? 'global:emergency_now')];
        }

        if ($emergencyRefs !== null) {
            foreach (array_values(array_unique($emergencyRefs)) as $ref) {
                $items[] = ['flow' => null, 'outcome' => $this->presenter->present($this->resolveRef($flows, $ref))];
            }
        } else {
            foreach ($flows as $flow) {
                if ($flow->outcome_id === null) {
                    continue;
                }

                $items[] = [
                    'flow' => ['key' => $flow->flow_key, 'title' => $flow->version->definition['title']],
                    'outcome' => $this->presenter->present($this->resolveOutcome($flow, $flow->outcome_id)),
                ];
            }

            usort($items, fn ($a, $b) => TriageOutcomeLevel::from($b['outcome']['level'])->rank() <=> TriageOutcomeLevel::from($a['outcome']['level'])->rank());
        }

        return [
            'session_id' => $session->id,
            'stage' => 'result',
            'emergency_stopped' => (bool) $session->emergency_stopped,
            'level' => $session->outcome_level ?? TriageOutcomeLevel::EmergencyNow->value,
            'reason' => match (true) {
                $fallback => 'fallback',
                $flows->first()?->outcome_id === self::SHORTCUT_OUTCOME || ($flows->isEmpty() && $session->emergency_stopped) => 'shortcut',
                $flows->first()?->outcome_id === self::RED_FLAG_OUTCOME => 'red_flag',
                default => 'answers',
            },
            'flows' => $this->flowRefs($flows),
            'outcomes' => $items,
        ];
    }

    // ------------------------------------------------------------- helpers

    private function walk(TriageSessionFlow $flow, Demographics $demo, array $answers): WalkResult
    {
        $prefix = self::answerKey($flow->flow_key, '');
        $flowAnswers = [];

        foreach ($answers as $key => $values) {
            if (str_starts_with($key, $prefix)) {
                $flowAnswers[substr($key, strlen($prefix))] = $values;
            }
        }

        return $this->walker->walk($flow->version->definition, $demo->toConditionValues(), $flowAnswers);
    }

    /**
     * The combined red-flag screen: global flags, then each flow's, filtered
     * by the demographics.
     *
     * @param  Collection<int, TriageSessionFlow>  $flows
     * @return list<array{code: string, label: string, help: string|null, group: string|null, outcome: string}>
     */
    private function screenItems(Demographics $demo, Collection $flows): array
    {
        $evaluator = new ConditionEvaluator($demo->toConditionValues(), []);
        $items = [];

        foreach ($this->global()->redFlags as $flag) {
            if (! array_key_exists('when', $flag) || $evaluator->evaluate($flag['when'])) {
                $items[] = [
                    'code' => 'global.'.$flag['code'],
                    'label' => $flag['label'],
                    'help' => $flag['help'] ?? null,
                    'group' => null,
                    'outcome' => $flag['outcome'] ?? 'global:emergency_now',
                ];
            }
        }

        foreach ($flows as $flow) {
            foreach ($flow->version->definition['red_flags'] as $flag) {
                if (array_key_exists('when', $flag) && ! $evaluator->evaluate($flag['when'])) {
                    continue;
                }

                $outcome = $flag['outcome'] ?? 'global:emergency_now';

                $items[] = [
                    'code' => $flow->flow_key.'.'.$flag['code'],
                    'label' => $flag['label'],
                    'help' => $flag['help'] ?? null,
                    'group' => $flow->flow_key,
                    'outcome' => str_starts_with($outcome, 'global:') ? $outcome : $flow->flow_key.':'.$outcome,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, TriageSessionFlow>  $flows
     * @return array<string, mixed> the outcome definition plus `ref`
     */
    private function resolveRef(Collection $flows, string $ref): array
    {
        if (str_starts_with($ref, 'global:')) {
            $id = substr($ref, 7);

            return ['ref' => $ref, 'id' => $id] + ($this->global()->outcome($id) ?? $this->global()->outcome('emergency_now') ?? []);
        }

        [$key, $id] = array_pad(explode(':', $ref, 2), 2, '');
        $flow = $flows->firstWhere('flow_key', $key);

        return $flow !== null ? $this->resolveOutcome($flow, $id) : $this->resolveRef($flows, 'global:emergency_now');
    }

    /** @return array<string, mixed> */
    private function resolveOutcome(TriageSessionFlow $flow, string $outcomeId): array
    {
        if (str_starts_with($outcomeId, 'global:') || in_array($outcomeId, [self::RED_FLAG_OUTCOME, self::SHORTCUT_OUTCOME], true)) {
            return $this->resolveRef(collect(), str_starts_with($outcomeId, 'global:') ? $outcomeId : 'global:emergency_now');
        }

        $outcome = $flow->version->definition['outcomes'][$outcomeId] ?? null;

        if (! is_array($outcome)) {
            return $this->resolveRef(collect(), 'global:emergency_now');
        }

        return ['ref' => $flow->flow_key.':'.$outcomeId, 'id' => $outcomeId] + $outcome;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function publicNode(string $id, array $node): array
    {
        $public = ['id' => $id, 'type' => $node['type']];

        foreach (['kind', 'text', 'help', 'title', 'unit', 'alt_units', 'min', 'max', 'step', 'allow_unknown', 'allow_unsure', 'optional', 'min_label', 'max_label'] as $field) {
            if (array_key_exists($field, $node)) {
                $public[$field] = $node[$field];
            }
        }

        if (isset($node['options'])) {
            $public['options'] = array_map(fn (array $o) => array_intersect_key($o, array_flip(['value', 'label', 'help', 'exclusive'])), $node['options']);
        }

        return $public;
    }

    /** Questions still possible on the longest route from this node (progress bar). */
    private function longestRemaining(array $definition, string $from): int
    {
        $memo = [];
        $nodes = $definition['nodes'];

        $longest = function (string $id) use (&$longest, &$memo, $nodes): int {
            if (! isset($nodes[$id])) {
                return 0;
            }

            if (isset($memo[$id])) {
                return $memo[$id];
            }

            $memo[$id] = 1;
            $next = $nodes[$id]['next'] ?? null;
            $targets = is_string($next) ? [$next] : array_column(is_array($next) ? $next : [], 'goto');
            $best = 0;

            foreach ($targets as $target) {
                $best = max($best, $longest((string) $target));
            }

            return $memo[$id] = 1 + $best;
        };

        return $longest($from);
    }

    /** @return Collection<int, TriageSessionFlow> */
    private function sessionFlows(TriageSession $session): Collection
    {
        return $session->sessionFlows()->with('version')->get();
    }

    /**
     * @param  Collection<int, TriageSessionFlow>  $flows
     * @return list<array{key: string, title: string}>
     */
    private function flowRefs(Collection $flows): array
    {
        return $flows->map(fn (TriageSessionFlow $f) => ['key' => $f->flow_key, 'title' => $f->version->definition['title']])->values()->all();
    }

    /** @return array<string, list<string>> */
    private function answersMap(TriageSession $session): array
    {
        return $session->answers()->get()->mapWithKeys(fn (TriageSessionAnswer $a) => [$a->step_key => array_values($a->values)])->all();
    }

    private function demographics(TriageSession $session): Demographics
    {
        $demo = Demographics::fromAnswerRows($this->answersMap($session));

        if ($demo === null) {
            throw ValidationException::withMessages(['session' => [__('api.guidance.v2.demographics_required')]]);
        }

        return $demo;
    }

    private function ensureScreened(TriageSession $session): void
    {
        if (! $session->answers()->where('step_key', self::SCREEN_KEY)->exists()) {
            throw ValidationException::withMessages(['session' => [__('api.guidance.v2.screen_required')]]);
        }
    }

    private function ensureOpen(TriageSession $session): void
    {
        if ($session->isCompleted()) {
            throw ValidationException::withMessages(['session' => [__('api.guidance.session_complete')]]);
        }
    }

    /**
     * Discards everything recorded after a stage: 'demographics' clears the
     * chosen symptoms, the screen and all flow answers; 'symptoms' clears the
     * screen and the flow answers.
     */
    private function clearAfter(TriageSession $session, string $stage): void
    {
        $session->answers()->where('step_key', self::SCREEN_KEY)->delete();
        $session->answers()->where('step_key', 'like', 'flow.%')->delete();

        if ($stage === 'demographics') {
            $session->sessionFlows()->delete();
        }
    }

    /** @param  list<string>  $values */
    private function putAnswer(TriageSession $session, string $key, array $values): void
    {
        TriageSessionAnswer::query()->updateOrCreate(
            ['triage_session_id' => $session->id, 'step_key' => $key],
            ['values' => $values],
        );
    }

    public static function answerKey(string $flowKey, string $nodeId): string
    {
        return 'flow.'.$flowKey.'.'.$nodeId;
    }

    private function global(): GlobalScreen
    {
        return $this->global ??= GlobalScreen::load();
    }
}
