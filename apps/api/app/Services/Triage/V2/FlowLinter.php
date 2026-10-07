<?php

namespace App\Services\Triage\V2;

use App\Enums\TriageOutcomeLevel;

/**
 * Checks flow files against docs/triage-flows.md before they can be imported
 * or published: shape, graph (every path ends in an outcome, nothing
 * unreachable, no cycles), red flags first and cited, emergency outcomes with
 * 194/112, safety-netting, special populations, sources and wording.
 */
final class FlowLinter
{
    public const SCHEMA = 'zdravje.triage/1';

    public const BODY_AREAS = ['head', 'eyes', 'ears', 'mouth', 'throat', 'chest', 'abdomen', 'pelvis', 'back', 'arms', 'legs', 'skin', 'general', 'mind'];

    public const KINDS = ['single', 'multi', 'yes_no', 'number', 'scale'];

    public const UNITS = ['celsius', 'minutes', 'hours', 'days', 'weeks', 'months', 'years', 'mmhg', 'mmol_l', 'bpm', 'kg', 'count'];

    public const TIME_UNITS = ['minutes', 'hours', 'days', 'weeks', 'months', 'years'];

    public const SETTINGS = ['emergency_department', 'on_call', 'gp', 'specialist', 'gynecology', 'pediatrics', 'dentist', 'pharmacy', 'mental_health', 'self_care'];

    public const FACILITY_TYPES = ['clinic', 'hospital', 'laboratory'];

    public const POPULATIONS = ['infant_0_3m', 'infant_3_12m', 'child', 'pregnancy', 'older_adult', 'chronic'];

    private const ID = '/^[a-z][a-z0-9_]*$/';

    private const KEY = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const VALUE = '/^[a-z0-9][a-z0-9_]*$/';

    /** Letters of other Cyrillic alphabets: the copy must be Macedonian. */
    private const FOREIGN_CYRILLIC = '/[йщъыьэюяёЙЩЪЫЬЭЮЯЁ]/u';

    /** Claims docs/triage-safety.md bans from outcome copy (warned, a human decides). */
    private const BANNED_WORDING = ['дијагноз', 'сигурно', 'дефинитивно', 'рецепт', 'доза'];

    private LintReport $report;

    /** @var array<string, mixed> */
    private array $flow;

    private GlobalScreen $global;

    /** @var array<string, list<string>> node id => ids that can precede it */
    private array $ancestors = [];

    /** @var array<string, list<string>> demo field => values compared against */
    private array $demoRefs = [];

    public function lintGlobal(GlobalScreen $global, ?LintReport $report = null): LintReport
    {
        $this->report = $report ?? new LintReport;
        $this->global = $global;
        $this->flow = ['nodes' => [], 'outcomes' => [], 'sources' => []];

        foreach (['emergency_now' => false, 'crisis' => true] as $required => $crisis) {
            $outcome = $global->outcome($required);

            if ($outcome === null) {
                $this->report->error('global', "outcome \"{$required}\" is required");

                continue;
            }

            if (($outcome['level'] ?? null) !== TriageOutcomeLevel::EmergencyNow->value || (bool) ($outcome['crisis'] ?? false) !== $crisis) {
                $this->report->error("global.outcomes.{$required}", 'must be level emergency_now'.($crisis ? ' with "crisis": true' : ''));
            }
        }

        foreach ($global->outcomes as $id => $outcome) {
            $this->lintOutcome("global.outcomes.{$id}", $outcome);
        }

        $codes = [];

        foreach ($global->redFlags as $index => $flag) {
            $where = "global.red_flags[{$index}]";
            $this->lintRedFlag($where, $flag, globalOnly: true);
            $code = $flag['code'] ?? null;

            if (is_string($code) && in_array($code, $codes, true)) {
                $this->report->error($where, "duplicate code \"{$code}\"");
            }

            $codes[] = $code;
        }

        if ($global->redFlags === []) {
            $this->report->error('global', 'the global red-flag screen is empty');
        }

        foreach ($global->sources as $id => $source) {
            $this->lintSource("global.sources.{$id}", $source);
        }

        return $this->report;
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    public function lint(array $flow, GlobalScreen $global, ?string $fileKey = null): LintReport
    {
        $this->report = new LintReport;
        $this->flow = $flow;
        $this->global = $global;
        $this->ancestors = [];
        $this->demoRefs = [];
        $where = 'flow';

        if (($flow['schema'] ?? null) !== self::SCHEMA) {
            $this->report->error($where, 'schema must be "'.self::SCHEMA.'"');
        }

        $key = $flow['key'] ?? null;

        if (! is_string($key) || preg_match(self::KEY, $key) !== 1 || strlen($key) > 64) {
            $this->report->error('key', 'must be kebab-case, at most 64 characters');
        } elseif ($fileKey !== null && $fileKey !== $key) {
            $this->report->error('key', "must equal the file name (\"{$fileKey}\")");
        }

        $this->text('title', $flow['title'] ?? null, 80);
        $this->internal('summary_en', $flow['summary_en'] ?? null);

        $bands = $flow['audience']['age_bands'] ?? null;

        if (! $this->isListOf($bands, Demographics::AGE_BANDS) || $bands === []) {
            $this->report->error('audience.age_bands', 'must be a non-empty list of age bands');
            $bands = [];
        }

        $rank = $flow['urgency_rank'] ?? null;

        if (! is_int($rank) || $rank < 1 || $rank > 100) {
            $this->report->error('urgency_rank', 'must be an integer 1–100');
        }

        if (! $this->isListOf($flow['body_areas'] ?? null, self::BODY_AREAS) || ($flow['body_areas'] ?? []) === []) {
            $this->report->error('body_areas', 'must be a non-empty list of: '.implode(', ', self::BODY_AREAS));
        }

        $this->lintSearchTerms($flow['search_terms'] ?? null);

        $nodes = $flow['nodes'] ?? null;
        $outcomes = $flow['outcomes'] ?? null;

        if (! is_array($nodes) || $nodes === [] || array_is_list($nodes)) {
            $this->report->error('nodes', 'must be a non-empty object');
            $nodes = [];
        }

        if (! is_array($outcomes) || $outcomes === [] || array_is_list($outcomes)) {
            $this->report->error('outcomes', 'must be a non-empty object');
            $outcomes = [];
        }

        $this->flow['nodes'] = $nodes;
        $this->flow['outcomes'] = $outcomes;

        foreach (array_keys($nodes) as $id) {
            if (preg_match(self::ID, (string) $id) !== 1 || strlen((string) $id) > 48) {
                $this->report->error("nodes.{$id}", 'id must be snake_case, at most 48 characters');
            }

            if (array_key_exists($id, $outcomes)) {
                $this->report->error("nodes.{$id}", 'id is also used by an outcome');
            }
        }

        foreach (array_keys($outcomes) as $id) {
            if (preg_match(self::ID, (string) $id) !== 1 || strlen((string) $id) > 48) {
                $this->report->error("outcomes.{$id}", 'id must be snake_case, at most 48 characters');
            }
        }

        $this->lintSources($flow['sources'] ?? null);

        // Shapes first; the graph checks rely on them.
        foreach ($nodes as $id => $node) {
            $this->lintNodeShape("nodes.{$id}", (array) $node);
        }

        foreach ($outcomes as $id => $outcome) {
            $this->lintOutcome("outcomes.{$id}", (array) $outcome);
        }

        $entry = $flow['entry'] ?? null;

        if (! is_string($entry) || ! isset($nodes[$entry])) {
            $this->report->error('entry', 'must name a node');
        }

        $graphLinted = $this->report->ok();

        if ($graphLinted) {
            $this->lintGraph((string) $entry);
        }

        $redFlags = $flow['red_flags'] ?? null;

        if (! is_array($redFlags) || $redFlags === [] || ! array_is_list($redFlags)) {
            $this->report->error('red_flags', 'every flow asks its own red flags first: at least one is required');
            $redFlags = [];
        }

        $codes = [];
        $globalLabels = array_map(fn ($f) => mb_strtolower(trim((string) ($f['label'] ?? ''))), $global->redFlags);

        foreach ($redFlags as $index => $flag) {
            $w = "red_flags[{$index}]";
            $this->lintRedFlag($w, (array) $flag, globalOnly: false);
            $code = $flag['code'] ?? null;

            if (is_string($code) && in_array($code, $codes, true)) {
                $this->report->error($w, "duplicate code \"{$code}\"");
            }

            $codes[] = $code;

            if (in_array(mb_strtolower(trim((string) ($flag['label'] ?? ''))), $globalLabels, true)) {
                $this->report->warn($w, 'repeats a global red flag');
            }
        }

        $this->lintScores($flow['scores'] ?? []);

        // Without a sound graph there are no ancestors to check against; the
        // conditions are still read (shape, populations) with every answer visible.
        foreach ($nodes as $id => $node) {
            $this->lintRouting((string) $id, (array) $node, $graphLinted);
        }

        $this->lintPopulations($bands, $flow['populations'] ?? []);

        return $this->report;
    }

    // ---------------------------------------------------------------- nodes

    /** @param  array<string, mixed>  $node */
    private function lintNodeShape(string $where, array $node): void
    {
        $type = $node['type'] ?? null;

        if ($type === 'info') {
            $this->text("{$where}.title", $node['title'] ?? null, 80);
            $this->text("{$where}.text", $node['text'] ?? null, 600);
            $this->lintNextShape($where, $node['next'] ?? null);

            return;
        }

        if ($type !== 'question') {
            $this->report->error($where, 'type must be "question" or "info"');

            return;
        }

        $this->text("{$where}.text", $node['text'] ?? null, 300);

        if (array_key_exists('help', $node)) {
            $this->text("{$where}.help", $node['help'], 500);
        }

        $kind = $node['kind'] ?? null;

        if (! in_array($kind, self::KINDS, true)) {
            $this->report->error("{$where}.kind", 'must be one of: '.implode(', ', self::KINDS));
        }

        if ($kind === 'single' || $kind === 'multi') {
            $this->lintOptions($where, $node['options'] ?? null, $kind === 'single' ? 12 : 15, $kind === 'multi');
        } elseif (array_key_exists('options', $node)) {
            $this->report->error("{$where}.options", 'only single and multi questions have options');
        }

        if ($kind === 'number') {
            $unit = $node['unit'] ?? null;

            if (! in_array($unit, self::UNITS, true)) {
                $this->report->error("{$where}.unit", 'must be one of: '.implode(', ', self::UNITS));
            }

            if (! is_numeric($node['min'] ?? null) || ! is_numeric($node['max'] ?? null) || $node['min'] >= $node['max']) {
                $this->report->error($where, 'needs numeric "min" < "max"');
            }

            if (array_key_exists('step', $node) && (! is_numeric($node['step']) || $node['step'] <= 0)) {
                $this->report->error("{$where}.step", 'must be a positive number');
            }

            if (array_key_exists('alt_units', $node)) {
                if (! in_array($unit, self::TIME_UNITS, true) || ! $this->isListOf($node['alt_units'], self::TIME_UNITS) || in_array($unit, $node['alt_units'], true)) {
                    $this->report->error("{$where}.alt_units", 'only time units, other than "unit", may have alternatives');
                }
            }
        }

        if ($kind === 'scale') {
            foreach (['min_label', 'max_label'] as $label) {
                if (array_key_exists($label, $node)) {
                    $this->text("{$where}.{$label}", $node[$label], 40);
                }
            }
        }

        $this->lintNextShape($where, $node['next'] ?? null);
    }

    private function lintOptions(string $where, mixed $options, int $max, bool $multi): void
    {
        if (! is_array($options) || ! array_is_list($options) || count($options) < 2 || count($options) > $max) {
            $this->report->error("{$where}.options", "needs 2–{$max} options");

            return;
        }

        $values = [];

        foreach ($options as $index => $option) {
            $w = "{$where}.options[{$index}]";
            $value = $option['value'] ?? null;

            if (! is_string($value) || preg_match(self::VALUE, $value) !== 1 || in_array($value, ['unknown', 'seen'], true)) {
                $this->report->error($w, 'value must be snake_case (and not "unknown" or "seen")');
            } elseif (in_array($value, $values, true)) {
                $this->report->error($w, "duplicate value \"{$value}\"");
            }

            $values[] = $value;
            $this->text("{$w}.label", $option['label'] ?? null, 160);

            if (array_key_exists('help', $option)) {
                $this->text("{$w}.help", $option['help'], 200);
            }

            if (! $multi && array_key_exists('exclusive', $option)) {
                $this->report->error($w, '"exclusive" only applies to multi questions');
            }
        }
    }

    private function lintNextShape(string $where, mixed $next): void
    {
        if (is_string($next)) {
            return;
        }

        if (! is_array($next) || ! array_is_list($next) || $next === []) {
            $this->report->error("{$where}.next", 'must be a target id or a non-empty list of branches');

            return;
        }

        $last = count($next) - 1;

        foreach ($next as $index => $branch) {
            if (! is_array($branch) || ! is_string($branch['goto'] ?? null)) {
                $this->report->error("{$where}.next[{$index}]", 'every branch needs "goto"');

                continue;
            }

            if ($index === $last && array_key_exists('when', $branch)) {
                $this->report->error("{$where}.next[{$index}]", 'the last branch is the default and must not have "when"');
            }

            if ($index !== $last && ! array_key_exists('when', $branch)) {
                $this->report->error("{$where}.next[{$index}]", 'only the last branch may omit "when"');
            }
        }
    }

    // ---------------------------------------------------------------- graph

    private function lintGraph(string $entry): void
    {
        $nodes = $this->flow['nodes'];
        $outcomes = $this->flow['outcomes'];
        $edges = [];

        foreach ($nodes as $id => $node) {
            $edges[$id] = [];

            foreach ($this->targets($node['next'] ?? null) as $target) {
                if (str_starts_with($target, 'global:')) {
                    if ($this->global->outcome(substr($target, 7)) === null) {
                        $this->report->error("nodes.{$id}.next", "unknown global outcome \"{$target}\"");
                    }

                    continue;
                }

                if (! isset($nodes[$target]) && ! isset($outcomes[$target])) {
                    $this->report->error("nodes.{$id}.next", "unknown target \"{$target}\"");

                    continue;
                }

                $edges[$id][] = $target;
            }
        }

        // Cycles (iterative DFS with colours).
        $state = [];

        foreach (array_keys($nodes) as $start) {
            if (isset($state[$start])) {
                continue;
            }

            $stack = [[$start, 0]];
            $state[$start] = 1;

            while ($stack !== []) {
                [$node, $i] = array_pop($stack);
                $children = $edges[$node] ?? [];

                if ($i < count($children)) {
                    $stack[] = [$node, $i + 1];
                    $child = $children[$i];

                    if (! isset($nodes[$child])) {
                        continue;
                    }

                    if (($state[$child] ?? 0) === 1) {
                        $this->report->error("nodes.{$child}", 'is part of a cycle');
                    } elseif (! isset($state[$child])) {
                        $state[$child] = 1;
                        $stack[] = [$child, 0];
                    }
                } else {
                    $state[$node] = 2;
                }
            }
        }

        // Reachability from the entry (red flags' local outcomes count too).
        $reached = [$entry => true];
        $queue = [$entry];

        while ($queue !== []) {
            $node = array_shift($queue);

            foreach ($edges[$node] ?? [] as $child) {
                if (! isset($reached[$child])) {
                    $reached[$child] = true;
                    $queue[] = $child;
                }
            }
        }

        foreach ($this->flow['red_flags'] ?? [] as $flag) {
            if (is_array($flag) && is_string($flag['outcome'] ?? null)) {
                $reached[$flag['outcome']] = true;
            }
        }

        foreach (array_keys($nodes) as $id) {
            if (! isset($reached[$id])) {
                $this->report->error("nodes.{$id}", 'is unreachable from the entry');
            }
        }

        foreach (array_keys($outcomes) as $id) {
            if (! isset($reached[$id])) {
                $this->report->error("outcomes.{$id}", 'is never reached');
            }
        }

        // Ancestors, for "this condition can only see earlier answers".
        foreach (array_keys($nodes) as $id) {
            $this->ancestors[$id] = [];
        }

        foreach ($edges as $from => $children) {
            foreach ($children as $child) {
                if (isset($nodes[$child])) {
                    $this->ancestors[$child][] = $from;
                }
            }
        }

        $closure = [];

        foreach (array_keys($nodes) as $id) {
            $seen = [];
            $queue = $this->ancestors[$id];

            while ($queue !== []) {
                $a = array_pop($queue);

                if (isset($seen[$a])) {
                    continue;
                }

                $seen[$a] = true;
                array_push($queue, ...$this->ancestors[$a]);
            }

            $closure[$id] = array_keys($seen);
        }

        $this->ancestors = $closure;
    }

    /** @return list<string> */
    private function targets(mixed $next): array
    {
        if (is_string($next)) {
            return [$next];
        }

        $targets = [];

        foreach (is_array($next) ? $next : [] as $branch) {
            if (is_array($branch) && is_string($branch['goto'] ?? null)) {
                $targets[] = $branch['goto'];
            }
        }

        return $targets;
    }

    /** @param  array<string, mixed>  $node */
    private function lintRouting(string $id, array $node, bool $checkOrder): void
    {
        $next = $node['next'] ?? null;
        $visible = $checkOrder ? [...($this->ancestors[$id] ?? []), $id] : ['*'];
        $unknownHandled = false;

        foreach (is_array($next) ? $next : [] as $index => $branch) {
            if (is_array($branch) && array_key_exists('when', $branch)) {
                $this->lintCondition("nodes.{$id}.next[{$index}].when", $branch['when'], $visible);

                if ($this->mentionsUnknown($branch['when'], $id)) {
                    $unknownHandled = true;
                }
            }
        }

        if (($node['kind'] ?? null) === 'number' && ($node['allow_unknown'] ?? false) && ! $unknownHandled) {
            $this->report->warn("nodes.{$id}", 'allows "unknown" but no branch routes it explicitly');
        }
    }

    private function mentionsUnknown(mixed $condition, string $node): bool
    {
        if (! is_array($condition)) {
            return false;
        }

        if (($condition['answer'] ?? null) === $node) {
            return ($condition['eq'] ?? null) === AnswerNormalizer::UNKNOWN
                || (is_array($condition['in'] ?? null) && in_array(AnswerNormalizer::UNKNOWN, $condition['in'], true));
        }

        foreach (['all', 'any'] as $combinator) {
            foreach (is_array($condition[$combinator] ?? null) ? $condition[$combinator] : [] as $child) {
                if ($this->mentionsUnknown($child, $node)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ----------------------------------------------------------- conditions

    /**
     * @param  list<string>|null  $visibleAnswers  nodes whose answers this condition may read; null = demographics only
     */
    private function lintCondition(string $where, mixed $condition, ?array $visibleAnswers, bool $allowScores = true): void
    {
        if (! is_array($condition) || $condition === [] || array_is_list($condition)) {
            $this->report->error($where, 'a condition must be an object');

            return;
        }

        foreach (['all', 'any'] as $combinator) {
            if (array_key_exists($combinator, $condition)) {
                if (count($condition) !== 1 || ! is_array($condition[$combinator]) || ! array_is_list($condition[$combinator]) || $condition[$combinator] === []) {
                    $this->report->error($where, "\"{$combinator}\" needs a non-empty list and nothing else");

                    return;
                }

                foreach ($condition[$combinator] as $i => $child) {
                    $this->lintCondition("{$where}.{$combinator}[{$i}]", $child, $visibleAnswers, $allowScores);
                }

                return;
            }
        }

        if (array_key_exists('not', $condition)) {
            if (count($condition) !== 1) {
                $this->report->error($where, '"not" takes one condition and nothing else');

                return;
            }

            $this->lintCondition("{$where}.not", $condition['not'], $visibleAnswers, $allowScores);

            return;
        }

        $subjects = array_values(array_intersect(['answer', 'demo', 'score'], array_keys($condition)));

        if (count($subjects) !== 1) {
            $this->report->error($where, 'a leaf names exactly one of "answer", "demo", "score"');

            return;
        }

        $subject = $subjects[0];
        $ref = $condition[$subject];

        if ($subject === 'answer' && array_key_exists('answered', $condition)) {
            if (count($condition) !== 2 || ! is_bool($condition['answered'])) {
                $this->report->error($where, '"answered" takes true or false and no operator');
            }

            $this->lintAnswerRef($where, $ref, $visibleAnswers);

            return;
        }

        $operators = array_values(array_intersect(ConditionEvaluator::OPERATORS, array_keys($condition)));

        if (count($operators) !== 1 || count($condition) !== 2) {
            $this->report->error($where, 'a leaf needs exactly one operator: '.implode(', ', ConditionEvaluator::OPERATORS));

            return;
        }

        $operator = $operators[0];
        $operand = $condition[$operator];
        $numeric = in_array($operator, ConditionEvaluator::NUMERIC_OPERATORS, true);

        if ($numeric && ! ($operator === 'between' ? is_array($operand) && count($operand) === 2 && is_numeric($operand[0]) && is_numeric($operand[1]) && $operand[0] <= $operand[1] : is_numeric($operand))) {
            $this->report->error($where, "\"{$operator}\" needs ".($operator === 'between' ? '[min, max]' : 'a number'));

            return;
        }

        if (in_array($operator, ['in', 'includes_any', 'includes_all'], true) && (! is_array($operand) || $operand === [] || ! array_is_list($operand))) {
            $this->report->error($where, "\"{$operator}\" needs a non-empty list");

            return;
        }

        $operandValues = match (true) {
            $numeric => [],
            is_array($operand) => array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $operand),
            default => [is_scalar($operand) ? (string) $operand : ''],
        };

        if ($subject === 'score') {
            if (! $allowScores || $visibleAnswers === null) {
                $this->report->error($where, 'scores cannot be used here');
            } elseif (! is_string($ref) || ! array_key_exists($ref, $this->flow['scores'] ?? [])) {
                $this->report->error($where, 'unknown score "'.(is_string($ref) ? $ref : '').'"');
            } elseif (! $numeric && ! in_array($operator, ['eq', 'ne'], true)) {
                $this->report->error($where, 'scores are compared with numeric operators');
            }

            return;
        }

        if ($subject === 'demo') {
            $this->lintDemoLeaf($where, $ref, $operator, $operandValues, $numeric);

            return;
        }

        $node = $this->lintAnswerRef($where, $ref, $visibleAnswers);

        if ($node === null) {
            return;
        }

        $kind = $node['type'] === 'info' ? 'info' : ($node['kind'] ?? null);

        if ($numeric) {
            if (! in_array($kind, ['number', 'scale'], true)) {
                $this->report->error($where, "numeric operator on a {$kind} question");
            }

            return;
        }

        $setOperator = in_array($operator, ['includes', 'includes_any', 'includes_all'], true);

        if ($setOperator !== ($kind === 'multi')) {
            $this->report->error($where, $setOperator ? 'includes* only applies to multi questions' : 'use includes* on multi questions');

            return;
        }

        $allowed = match ($kind) {
            'single', 'multi' => array_map(fn ($o) => (string) ($o['value'] ?? ''), $node['options'] ?? []),
            'yes_no' => ($node['allow_unsure'] ?? false) ? ['yes', 'no', 'unsure'] : ['yes', 'no'],
            'number' => ($node['allow_unknown'] ?? false) ? [AnswerNormalizer::UNKNOWN] : [],
            'scale' => array_map('strval', range(0, 10)),
            default => [],
        };

        foreach ($operandValues as $value) {
            if (! in_array($value, $allowed, true)) {
                $this->report->error($where, "\"{$value}\" is not an answer that question can have");
            }
        }
    }

    /**
     * @param  list<string>|null  $visible
     * @return array<string, mixed>|null
     */
    private function lintAnswerRef(string $where, mixed $ref, ?array $visible): ?array
    {
        if ($visible === null) {
            $this->report->error($where, 'only demographics ("demo") can be used here');

            return null;
        }

        $node = is_string($ref) ? ($this->flow['nodes'][$ref] ?? null) : null;

        if (! is_array($node)) {
            $this->report->error($where, 'unknown question "'.(is_string($ref) ? $ref : '').'"');

            return null;
        }

        if ($visible !== ['*'] && ! in_array($ref, $visible, true)) {
            $this->report->error($where, "\"{$ref}\" is never answered before this point");
        }

        return $node;
    }

    /**
     * @param  list<string>  $values
     */
    private function lintDemoLeaf(string $where, mixed $field, string $operator, array $values, bool $numeric): void
    {
        if (! is_string($field) || ! in_array($field, Demographics::FIELDS, true)) {
            $this->report->error($where, 'unknown demographic "'.(is_string($field) ? $field : '').'"');

            return;
        }

        $this->demoRefs[$field] = array_values(array_unique([...($this->demoRefs[$field] ?? []), ...$values]));

        if (in_array($field, ['age_months', 'age_years'], true)) {
            if (! $numeric && ! in_array($operator, ['eq', 'ne', 'in'], true)) {
                $this->report->error($where, "{$field} is a number");
            }

            return;
        }

        if ($numeric) {
            $this->report->error($where, "numeric operator on {$field}");

            return;
        }

        $setOperator = in_array($operator, ['includes', 'includes_any', 'includes_all'], true);

        if ($setOperator !== ($field === 'conditions')) {
            $this->report->error($where, $field === 'conditions' ? 'use includes* on conditions' : 'includes* only applies to conditions');

            return;
        }

        $allowed = match ($field) {
            'age_band' => Demographics::AGE_BANDS,
            'sex' => Demographics::SEXES,
            'pregnancy' => Demographics::PREGNANCY,
            'conditions' => Demographics::CONDITIONS,
        };

        foreach ($values as $value) {
            if (! in_array($value, $allowed, true)) {
                $this->report->error($where, "\"{$value}\" is not a value of {$field}");
            }
        }

        if ($field === 'pregnancy' && in_array('pregnant', $values, true) && ! in_array('unsure', $values, true) && $operator !== 'ne') {
            $this->report->warn($where, 'treat "unsure" like "pregnant" (add it)');
        }
    }

    // ------------------------------------------------------------ red flags

    /** @param  array<string, mixed>  $flag */
    private function lintRedFlag(string $where, array $flag, bool $globalOnly): void
    {
        $code = $flag['code'] ?? null;

        if (! is_string($code) || preg_match(self::ID, $code) !== 1 || strlen($code) > 48) {
            $this->report->error("{$where}.code", 'must be snake_case, at most 48 characters');
        }

        $this->text("{$where}.label", $flag['label'] ?? null, 200);

        if (array_key_exists('help', $flag)) {
            $this->text("{$where}.help", $flag['help'], 300);
        }

        if (array_key_exists('when', $flag)) {
            // The screen comes before any answer: demographics only.
            $this->lintCondition("{$where}.when", $flag['when'], null, allowScores: false);
        }

        $outcomeRef = $flag['outcome'] ?? 'global:emergency_now';
        $outcome = null;

        if (! is_string($outcomeRef)) {
            $this->report->error("{$where}.outcome", 'must be an outcome id');
        } elseif (str_starts_with($outcomeRef, 'global:')) {
            $outcome = $this->global->outcome(substr($outcomeRef, 7));
        } elseif ($globalOnly) {
            $this->report->error("{$where}.outcome", 'global red flags lead to global outcomes ("global:<id>")');
        } else {
            $outcome = $this->flow['outcomes'][$outcomeRef] ?? null;
        }

        if (is_string($outcomeRef) && $outcome === null) {
            $this->report->error("{$where}.outcome", "unknown outcome \"{$outcomeRef}\"");
        } elseif (is_array($outcome) && ($outcome['level'] ?? null) !== TriageOutcomeLevel::EmergencyNow->value) {
            $this->report->error("{$where}.outcome", 'a red flag must lead to an emergency_now outcome');
        }

        $source = $flag['source'] ?? null;

        if (! is_string($source) || $source === '') {
            $this->report->error("{$where}.source", 'every red flag cites a source');
        } elseif (! $this->sourceExists($source)) {
            $this->report->error("{$where}.source", "unknown source \"{$source}\"");
        }
    }

    // ------------------------------------------------------------- outcomes

    /** @param  array<string, mixed>  $outcome */
    private function lintOutcome(string $where, array $outcome): void
    {
        $level = TriageOutcomeLevel::tryFrom((string) ($outcome['level'] ?? ''));

        if ($level === null) {
            $this->report->error("{$where}.level", 'must be one of: '.implode(', ', TriageOutcomeLevel::values()));
        }

        $this->text("{$where}.title", $outcome['title'] ?? null, 90, banned: true);
        $this->text("{$where}.summary", $outcome['summary'] ?? null, 300, banned: true);

        foreach (['reasons', 'do_now', 'watch_for'] as $field) {
            $items = $outcome[$field] ?? [];
            $required = $field !== 'watch_for' || $level !== TriageOutcomeLevel::EmergencyNow;

            if (! is_array($items) || ! array_is_list($items) || ($required && $items === [])) {
                $this->report->error("{$where}.{$field}", $field === 'watch_for'
                    ? 'safety-netting is required: at least one „Ако … веднаш …“ item'
                    : 'needs at least one item');

                continue;
            }

            foreach ($items as $i => $item) {
                $this->text("{$where}.{$field}[{$i}]", $item, 200, banned: true);
            }
        }

        if (($outcome['crisis'] ?? false) && $level !== TriageOutcomeLevel::EmergencyNow) {
            $this->report->error("{$where}.crisis", 'only an emergency_now outcome can be a crisis outcome');
        }

        $call = $outcome['call'] ?? null;

        if ($level === TriageOutcomeLevel::EmergencyNow) {
            $numbers = [];

            foreach (is_array($call) ? $call : [] as $i => $line) {
                $number = is_array($line) ? ($line['number'] ?? null) : null;

                if (! is_string($number) || preg_match('/^\+?[0-9 ]{3,20}$/', $number) !== 1) {
                    $this->report->error("{$where}.call[{$i}]", 'needs a phone "number"');

                    continue;
                }

                $this->text("{$where}.call[{$i}].label", $line['label'] ?? null, 80);
                $numbers[] = str_replace(' ', '', $number);
            }

            if (! in_array('194', $numbers, true) || ! in_array('112', $numbers, true)) {
                $this->report->error("{$where}.call", 'an emergency outcome must offer tel links to 194 and 112');
            }
        } elseif ($call !== null) {
            $this->report->error("{$where}.call", 'only emergency_now outcomes carry call links');
        }

        $care = $outcome['care'] ?? null;

        if (! is_array($care) || ! in_array($care['setting'] ?? null, self::SETTINGS, true)) {
            $this->report->error("{$where}.care.setting", 'must be one of: '.implode(', ', self::SETTINGS));
        }

        foreach ((array) ($care['specialties'] ?? []) as $specialty) {
            if (! is_string($specialty) || ! SpecialtyGroups::exists($specialty)) {
                $this->report->error("{$where}.care.specialties", 'unknown specialty key "'.(is_string($specialty) ? $specialty : '').'"');
            }
        }

        if (! $this->isListOf($care['facility_types'] ?? [], self::FACILITY_TYPES)) {
            $this->report->error("{$where}.care.facility_types", 'must be a list of: '.implode(', ', self::FACILITY_TYPES));
        }

        foreach ((array) ($outcome['sources'] ?? []) as $source) {
            if (! is_string($source) || ! $this->sourceExists($source)) {
                $this->report->error("{$where}.sources", 'unknown source "'.(is_string($source) ? $source : '').'"');
            }
        }
    }

    // ----------------------------------------------------- scores, sources

    private function lintScores(mixed $scores): void
    {
        if ($scores === []) {
            return;
        }

        if (! is_array($scores) || array_is_list($scores)) {
            $this->report->error('scores', 'must be an object');

            return;
        }

        foreach ($scores as $id => $score) {
            if (preg_match(self::ID, (string) $id) !== 1) {
                $this->report->error("scores.{$id}", 'id must be snake_case');
            }

            $items = $score['items'] ?? null;

            if (! is_array($items) || $items === [] || ! array_is_list($items)) {
                $this->report->error("scores.{$id}.items", 'needs at least one item');

                continue;
            }

            foreach ($items as $i => $item) {
                if (! is_numeric($item['points'] ?? null)) {
                    $this->report->error("scores.{$id}.items[{$i}].points", 'must be a number');
                }

                $this->lintCondition("scores.{$id}.items[{$i}].when", $item['when'] ?? null, ['*'], allowScores: false);
            }
        }
    }

    private function lintSources(mixed $sources): void
    {
        if (! is_array($sources) || $sources === [] || ! array_is_list($sources)) {
            $this->report->error('sources', 'every flow cites at least one public source');

            return;
        }

        $ids = [];

        foreach ($sources as $i => $source) {
            $this->lintSource("sources[{$i}]", (array) $source);
            $id = $source['id'] ?? null;

            if (in_array($id, $ids, true)) {
                $this->report->error("sources[{$i}]", "duplicate id \"{$id}\"");
            }

            $ids[] = $id;
        }
    }

    /** @param  array<string, mixed>  $source */
    private function lintSource(string $where, array $source): void
    {
        if (! is_string($source['id'] ?? null) || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $source['id']) !== 1) {
            $this->report->error("{$where}.id", 'must be a lower-case id');
        }

        if (! is_string($source['title'] ?? null) || trim($source['title']) === '') {
            $this->report->error("{$where}.title", 'is required');
        }

        $url = $source['url'] ?? null;
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        if (! is_string($url) || ! str_starts_with($url, 'https://') || ! is_string($host)) {
            $this->report->error("{$where}.url", 'must be an https URL');
        } else {
            $public = false;

            foreach ((array) config('triage.public_source_domains', []) as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    $public = true;
                }
            }

            if (! $public) {
                $this->report->warn("{$where}.url", "\"{$host}\" is not on the list of public sources; check it is public material");
            }
        }

        $accessed = $source['accessed'] ?? null;

        if (! is_string($accessed) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $accessed) !== 1) {
            $this->report->error("{$where}.accessed", 'must be a YYYY-MM-DD date');
        }
    }

    private function sourceExists(string $id): bool
    {
        foreach ($this->flow['sources'] ?? [] as $source) {
            if (is_array($source) && ($source['id'] ?? null) === $id) {
                return true;
            }
        }

        return isset($this->global->sources[$id]);
    }

    // ---------------------------------------------------------- populations

    /**
     * @param  list<string>  $bands
     */
    private function lintPopulations(array $bands, mixed $notes): void
    {
        if (! is_array($notes)) {
            $this->report->error('populations', 'must be an object');
            $notes = [];
        }

        foreach ($notes as $key => $note) {
            if (! in_array($key, self::POPULATIONS, true)) {
                $this->report->error("populations.{$key}", 'unknown population; use: '.implode(', ', self::POPULATIONS));
            } elseif (! is_string($note) || trim($note) === '') {
                $this->report->error("populations.{$key}", 'must be a non-empty note');
            }
        }

        // Red-flag `when`s were linted above and recorded their references.
        $bandRefs = $this->demoRefs['age_band'] ?? [];
        $byAge = isset($this->demoRefs['age_months']) || isset($this->demoRefs['age_years']);
        $children = ['child_1_4', 'child_5_12', 'teen_13_17'];

        $relevant = [
            'infant_0_3m' => [in_array('infant_0_3m', $bands, true), in_array('infant_0_3m', $bandRefs, true) || isset($this->demoRefs['age_months'])],
            'infant_3_12m' => [in_array('infant_3_12m', $bands, true), in_array('infant_3_12m', $bandRefs, true) || isset($this->demoRefs['age_months'])],
            'child' => [array_intersect($children, $bands) !== [], array_intersect($children, $bandRefs) !== [] || $byAge],
            'pregnancy' => [array_intersect(['teen_13_17', 'adult_18_64'], $bands) !== [], isset($this->demoRefs['pregnancy'])],
            'older_adult' => [in_array('older_65_plus', $bands, true), in_array('older_65_plus', $bandRefs, true) || isset($this->demoRefs['age_years'])],
            'chronic' => [$bands !== [], isset($this->demoRefs['conditions'])],
        ];

        foreach ($relevant as $population => [$applies, $referenced]) {
            if ($applies && ! $referenced && ! (is_string($notes[$population] ?? null) && trim($notes[$population]) !== '')) {
                $this->report->error('populations', "\"{$population}\" is in the audience but no condition handles it and no note explains why");
            }
        }
    }

    // ---------------------------------------------------------------- text

    private function lintSearchTerms(mixed $terms): void
    {
        if (! is_array($terms) || ! array_is_list($terms) || count($terms) < 3) {
            $this->report->error('search_terms', 'needs at least 3 terms');

            return;
        }

        foreach ($terms as $i => $term) {
            if (! is_string($term) || trim($term) === '' || mb_strlen($term) > 60) {
                $this->report->error("search_terms[{$i}]", 'must be a short non-empty string');

                continue;
            }

            if (mb_strtolower($term) !== $term) {
                $this->report->error("search_terms[{$i}]", 'must be lower case');
            }

            if (preg_match('/[a-z]/i', $term) === 1) {
                $this->report->warn("search_terms[{$i}]", 'Latin letters: the engine transliterates Latin input, write terms in Cyrillic');
            }

            $this->alphabet("search_terms[{$i}]", $term);
        }
    }

    private function text(string $where, mixed $value, int $max, bool $banned = false): void
    {
        if (! is_string($value) || trim($value) === '') {
            $this->report->error($where, 'is required');

            return;
        }

        if (mb_strlen($value) > $max) {
            $this->report->error($where, "at most {$max} characters");
        }

        $this->alphabet($where, $value);

        if ($banned) {
            $lower = mb_strtolower($value);

            foreach (self::BANNED_WORDING as $word) {
                if (str_contains($lower, $word)) {
                    $this->report->warn($where, "wording „{$word}“ — guidance must not diagnose or prescribe");
                }
            }
        }
    }

    private function internal(string $where, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            $this->report->error($where, 'is required');
        }
    }

    private function alphabet(string $where, string $value): void
    {
        if (preg_match(self::FOREIGN_CYRILLIC, $value) === 1) {
            $this->report->error($where, 'contains letters outside the Macedonian alphabet');
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function isListOf(mixed $value, array $allowed): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! in_array($item, $allowed, true)) {
                return false;
            }
        }

        return count($value) === count(array_unique($value));
    }
}
