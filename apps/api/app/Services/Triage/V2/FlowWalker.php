<?php

namespace App\Services\Triage\V2;

/**
 * Walks one flow definition from its entry with the answers given so far.
 *
 * The path is a pure function of the definition, the demographics and the
 * answers: the server never trusts a client's idea of "the next question".
 * Answers to nodes that are not on the resulting path are ignored.
 */
final class FlowWalker
{
    /**
     * @param  array<string, mixed>  $definition  a linted flow definition
     * @param  array<string, mixed>  $demo  Demographics::toConditionValues()
     * @param  array<string, list<string>>  $answers  node id => values
     */
    public function walk(array $definition, array $demo, array $answers): WalkResult
    {
        $nodes = $definition['nodes'] ?? [];
        $outcomes = $definition['outcomes'] ?? [];
        $current = $definition['entry'] ?? null;
        $path = [];
        $pathAnswers = [];
        $scores = $this->scores($definition, $demo, []);
        // A linted flow is acyclic; the bound only protects against a bug.
        $guard = count($nodes) + 2;

        while (is_string($current) && $guard-- > 0) {
            if (str_starts_with($current, 'global:') || array_key_exists($current, $outcomes)) {
                return new WalkResult($path, null, $current, $scores);
            }

            $node = $nodes[$current] ?? null;

            if (! is_array($node)) {
                break;
            }

            if (! array_key_exists($current, $answers)) {
                return new WalkResult($path, $current, null, $scores);
            }

            $path[] = $current;
            $pathAnswers[$current] = $answers[$current];
            $scores = $this->scores($definition, $demo, $pathAnswers);
            $current = $this->route($node['next'] ?? null, new ConditionEvaluator($demo, $pathAnswers, $scores));
        }

        // Unreachable for a linted flow. Fail closed: no guessed outcome.
        return new WalkResult($path, null, null, $scores, broken: true);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $demo
     * @param  array<string, list<string>>  $answers
     * @return array<string, int|float>
     */
    public function scores(array $definition, array $demo, array $answers): array
    {
        $scores = [];
        $evaluator = new ConditionEvaluator($demo, $answers);

        foreach ($definition['scores'] ?? [] as $id => $score) {
            $total = 0;

            foreach ($score['items'] ?? [] as $item) {
                if ($evaluator->evaluate($item['when'] ?? null)) {
                    $total += is_numeric($item['points'] ?? null) ? $item['points'] + 0 : 0;
                }
            }

            $scores[$id] = $total;
        }

        return $scores;
    }

    public function route(mixed $next, ConditionEvaluator $evaluator): ?string
    {
        if (is_string($next)) {
            return $next;
        }

        if (! is_array($next)) {
            return null;
        }

        foreach ($next as $branch) {
            if (! is_array($branch) || ! isset($branch['goto']) || ! is_string($branch['goto'])) {
                return null;
            }

            if (! array_key_exists('when', $branch) || $evaluator->evaluate($branch['when'])) {
                return $branch['goto'];
            }
        }

        return null;
    }
}
