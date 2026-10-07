<?php

namespace App\Services\Triage\V2;

/**
 * Lint rule: copy that names sex-, age- or pregnancy-specific things (config
 * `triage.demographic_keywords`) must not be reachable for a visitor it does
 * not fit. For every demographic profile the flow is offered to, the flow is
 * walked symbolically (demographic conditions evaluated, answer conditions
 * "maybe"), the red-flag screen is filtered by `when`, and every question,
 * info node, option, red flag and outcome that can be shown is checked.
 *
 * "unspecified" sex is never forbidden anything: under-triage is worse, so
 * sex-specific red flags stay and are worded neutrally.
 *
 * Justified exceptions: "demographics_ok_reason" on the node, outcome or red
 * flag, or at the top level of the flow (inherently sex-specific flows).
 */
final class DemographicAppropriateness
{
    /** Ages (in months) sampled: every band edge that matters. */
    private const AGES_MONTHS = [0, 6, 24, 96, 108, 120, 180, 360, 660, 672, 840];

    /** @var array<string, mixed> */
    private array $categories;

    private bool $waiveSexAndPregnancy = false;

    /**
     * @param  array<string, mixed>|null  $categories
     */
    public function __construct(?array $categories = null)
    {
        $this->categories = $categories ?? (array) config('triage.demographic_keywords', []);
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    public function lintFlow(array $flow, LintReport $report): void
    {
        // A flow that is inherently about one sex (vaginal bleeding, pregnancy,
        // emergency contraception) says so once: its sex and pregnancy
        // restrictions are waived, the age ones still apply.
        $this->waiveSexAndPregnancy = $this->hasReason($flow);
        $found = [];
        $bands = array_values(array_filter((array) ($flow['audience']['age_bands'] ?? []), 'is_string'));

        $this->walkProfiles($bands, function (Demographics $demo, string $label) use ($flow, &$found): void {
            foreach ($this->shown($flow, $demo) as $where => $item) {
                $this->collect($found, $where, $item, $demo, $label);
            }
        });

        $this->report($report, $found);
    }

    /**
     * @param  list<array<string, mixed>>  $redFlags  global red flags
     */
    public function lintGlobal(array $redFlags, LintReport $report): void
    {
        $this->waiveSexAndPregnancy = false;
        $found = [];

        $this->walkProfiles([], function (Demographics $demo, string $label) use ($redFlags, &$found): void {
            $evaluator = new ConditionEvaluator($demo->toConditionValues(), []);

            foreach ($redFlags as $index => $flag) {
                if (! array_key_exists('when', $flag) || $this->tri($flag['when'], $evaluator) !== false) {
                    $this->collect($found, "global.red_flags[{$index}]", $flag, $demo, $label);
                }
            }
        });

        $this->report($report, $found);
    }

    /**
     * @param  list<string>  $bands  empty = every band
     * @param  callable(Demographics, string): void  $each
     */
    private function walkProfiles(array $bands, callable $each): void
    {
        foreach (['female', 'male', 'unspecified'] as $sex) {
            foreach (self::AGES_MONTHS as $months) {
                $years = intdiv($months, 12);

                if ($bands !== [] && ! in_array($this->band($months), $bands, true)) {
                    continue;
                }

                $pregnancies = Demographics::pregnancyIsAsked($sex, $years)
                    ? ['pregnant', 'postpartum', 'not_pregnant', 'unsure']
                    : ['not_asked'];

                foreach ($pregnancies as $pregnancy) {
                    $each(new Demographics($months, $sex, $pregnancy, []), "sex={$sex}, age {$years} y".($months % 12 === 0 ? '' : " ({$months} mo)").", pregnancy={$pregnancy}");
                }
            }
        }
    }

    private function band(int $months): string
    {
        return (new Demographics($months, 'unspecified', 'not_asked', []))->ageBand();
    }

    /**
     * Everything the visitor can be shown, keyed by its location in the file.
     *
     * @param  array<string, mixed>  $flow
     * @return array<string, array<string, mixed>>
     */
    private function shown(array $flow, Demographics $demo): array
    {
        $evaluator = new ConditionEvaluator($demo->toConditionValues(), []);
        $nodes = (array) ($flow['nodes'] ?? []);
        $outcomes = (array) ($flow['outcomes'] ?? []);
        $shown = [];
        $outcomeIds = [];

        foreach ((array) ($flow['red_flags'] ?? []) as $index => $flag) {
            if (! is_array($flag) || (array_key_exists('when', $flag) && $this->tri($flag['when'], $evaluator) === false)) {
                continue;
            }

            $shown["red_flags[{$index}]"] = $flag;
            $target = $flag['outcome'] ?? null;

            if (is_string($target) && isset($outcomes[$target])) {
                $outcomeIds[$target] = true;
            }
        }

        // Fixpoint: a branch on the answer to a question that cannot have been
        // asked for this profile is false; once the question is reachable the
        // branch becomes "maybe". Reachability only grows, so this terminates.
        $reached = [];

        do {
            $before = count($reached);
            $queue = [$flow['entry'] ?? null];
            $seen = [];

            while ($queue !== []) {
                $id = array_shift($queue);

                if (! is_string($id) || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;

                if (isset($outcomes[$id])) {
                    $outcomeIds[$id] = true;

                    continue;
                }

                $node = $nodes[$id] ?? null;

                if (! is_array($node)) {
                    continue;
                }

                $reached[$id] = true;
                $shown["nodes.{$id}"] = $node;

                foreach ($this->successors($node['next'] ?? null, $evaluator, $reached) as $target) {
                    $queue[] = $target;
                }
            }
        } while (count($reached) > $before);

        foreach (array_keys($outcomeIds) as $id) {
            $shown["outcomes.{$id}"] = (array) $outcomes[$id];
        }

        return $shown;
    }

    /**
     * @param  array<string, true>  $reached  questions that can have been asked
     * @return list<string>
     */
    private function successors(mixed $next, ConditionEvaluator $evaluator, array $reached): array
    {
        if (is_string($next)) {
            return [$next];
        }

        $targets = [];

        foreach ((array) $next as $branch) {
            if (! is_array($branch) || ! is_string($branch['goto'] ?? null)) {
                continue;
            }

            $value = array_key_exists('when', $branch) ? $this->tri($branch['when'], $evaluator, $reached) : true;

            if ($value === false) {
                continue;
            }

            $targets[] = $branch['goto'];

            if ($value === true) {
                break;
            }
        }

        return $targets;
    }

    /**
     * Three-valued evaluation: demographics are known, answers to questions
     * that can have been asked and scores are not.
     *
     * @param  array<string, true>  $reached
     */
    private function tri(mixed $condition, ConditionEvaluator $evaluator, array $reached = []): ?bool
    {
        if (! is_array($condition) || $condition === []) {
            return null;
        }

        if (array_key_exists('all', $condition) || array_key_exists('any', $condition)) {
            $all = array_key_exists('all', $condition);
            $values = array_map(fn ($c) => $this->tri($c, $evaluator, $reached), (array) ($condition['all'] ?? $condition['any']));
            $decisive = ! $all;

            if (in_array($decisive, $values, true)) {
                return $decisive;
            }

            return in_array(null, $values, true) ? null : $all;
        }

        if (array_key_exists('not', $condition)) {
            $value = $this->tri($condition['not'], $evaluator, $reached);

            return $value === null ? null : ! $value;
        }

        if (isset($condition['demo'])) {
            return $evaluator->evaluate($condition);
        }

        if (isset($condition['answer']) && is_string($condition['answer']) && ! isset($reached[$condition['answer']])) {
            // Never asked: every comparison is false, "answered: false" is true.
            return array_key_exists('answered', $condition) ? ! $condition['answered'] : false;
        }

        return null;
    }

    /**
     * @param  array<string, array<string, array{stem: string, profile: string, context: string}>>  $found
     * @param  array<string, mixed>  $item
     */
    private function collect(array &$found, string $where, array $item, Demographics $demo, string $label): void
    {
        if ($this->hasReason($item)) {
            return;
        }

        $text = null;

        foreach ($this->categories as $name => $category) {
            if (! is_array($category) || $demo->ageYears() < (int) ($category['min_age_years'] ?? 0) || ! $this->forbidden((array) ($category['forbidden'] ?? []), $demo) || isset($found[$where][$name])) {
                continue;
            }

            $text ??= mb_strtolower($this->textOf($item));

            foreach ((array) ($category['stems'] ?? []) as $stem) {
                if (is_string($stem) && str_contains($text, mb_strtolower($stem))) {
                    $found[$where][$name] = ['stem' => $stem, 'profile' => $label, 'context' => $this->context($text, mb_strtolower($stem))];

                    break;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $forbidden
     */
    private function forbidden(array $forbidden, Demographics $demo): bool
    {
        return (! $this->waiveSexAndPregnancy && in_array($demo->sex, (array) ($forbidden['sex'] ?? []), true))
            || (! $this->waiveSexAndPregnancy && in_array($demo->pregnancy, (array) ($forbidden['pregnancy'] ?? []), true))
            || (isset($forbidden['age_years_lt']) && $demo->ageYears() < $forbidden['age_years_lt'])
            || (isset($forbidden['age_years_gte']) && $demo->ageYears() >= $forbidden['age_years_gte']);
    }

    /**
     * @param  array<string, array<string, array{stem: string, profile: string, context: string}>>  $found
     */
    private function report(LintReport $report, array $found): void
    {
        foreach ($found as $where => $categories) {
            foreach ($categories as $name => $hit) {
                $report->error($where, "mentions „{$hit['stem']}“ ({$name}: …{$hit['context']}…) but can be shown to {$hit['profile']}: guard the routing that leads here (or the red flag's \"when\") with a demographic condition, or give \"demographics_ok_reason\"");
            }
        }
    }

    private function context(string $text, string $stem): string
    {
        $at = (int) mb_strpos($text, $stem);

        return str_replace("\n", ' / ', mb_substr($text, max(0, $at - 25), 60));
    }

    /** @param  array<string, mixed>  $item */
    private function hasReason(array $item): bool
    {
        return is_string($item['demographics_ok_reason'] ?? null) && trim($item['demographics_ok_reason']) !== '';
    }

    /**
     * Every user-facing string of a node, outcome or red flag.
     *
     * @param  array<string, mixed>  $item
     */
    private function textOf(array $item): string
    {
        $parts = [];

        foreach (['title', 'text', 'help', 'label', 'summary', 'min_label', 'max_label'] as $key) {
            if (is_string($item[$key] ?? null)) {
                $parts[] = $item[$key];
            }
        }

        foreach (['options', 'reasons', 'do_now', 'watch_for'] as $key) {
            foreach ((array) ($item[$key] ?? []) as $entry) {
                $parts[] = is_array($entry) ? implode(' ', array_filter([$entry['label'] ?? null, $entry['help'] ?? null], 'is_string')) : (string) $entry;
            }
        }

        return implode("\n", $parts);
    }
}
