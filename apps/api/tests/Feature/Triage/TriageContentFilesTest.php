<?php

namespace Tests\Feature\Triage;

use Tests\TestCase;

/**
 * Structural self-check of the clinical guidance flows (database/data/triage/flows,
 * schema zdravje.triage/1 — docs/triage-flows.md). It does not depend on the engine:
 * the engine's linter (`php artisan triage:lint`) runs the full set of rules on
 * import; this test keeps the content branch honest on its own and pins the
 * safety rules that matter most (red flags first, 194/112 on every emergency,
 * crisis routing, safety-netting, population coverage, Macedonian alphabet).
 */
class TriageContentFilesTest extends TestCase
{
    private const SCHEMA = 'zdravje.triage/1';

    /** Files owned by the engine package, not by the content package. */
    private const ENGINE_FILES = ['general'];

    private const AGE_BANDS = ['infant_0_3m', 'infant_3_12m', 'child_1_4', 'child_5_12', 'teen_13_17', 'adult_18_64', 'older_65_plus'];

    private const LEVELS = ['emergency_now', 'urgent_same_day', 'see_doctor_24_48h', 'see_gp_this_week', 'pharmacy_advice', 'self_care_with_safety_net'];

    private const SETTINGS = ['emergency_department', 'on_call', 'gp', 'specialist', 'gynecology', 'pediatrics', 'dentist', 'pharmacy', 'mental_health', 'self_care'];

    private const FACILITY_TYPES = ['clinic', 'hospital', 'laboratory'];

    private const BODY_AREAS = ['head', 'eyes', 'ears', 'mouth', 'throat', 'chest', 'abdomen', 'pelvis', 'back', 'arms', 'legs', 'skin', 'general', 'mind'];

    private const KINDS = ['single', 'multi', 'yes_no', 'number', 'scale'];

    private const UNITS = ['celsius', 'minutes', 'hours', 'days', 'weeks', 'months', 'years', 'mmhg', 'mmol_l', 'bpm', 'kg', 'count'];

    private const DEMO_FIELDS = [
        'age_band' => self::AGE_BANDS,
        'sex' => ['female', 'male', 'unspecified'],
        'pregnancy' => ['pregnant', 'postpartum', 'not_pregnant', 'unsure', 'not_asked'],
        'conditions' => ['immunosuppressed', 'diabetes', 'heart_disease', 'lung_disease', 'kidney_disease', 'pregnancy_complication_history'],
        'age_months' => null,
        'age_years' => null,
    ];

    private const PUBLIC_DOMAINS = ['nhs.uk', 'nice.org.uk', 'who.int', 'cdc.gov', 'zdravstvo.gov.mk', 'iph.mk', 'fzo.org.mk'];

    /** Letters of the Russian/Serbian/Bulgarian alphabets that Macedonian does not use. */
    private const FOREIGN_LETTERS = '/[йщъыьэюяёЙЩЪЫЬЭЮЯЁ]/u';

    private const BANNED_IN_OUTCOMES = ['дијагноз', 'сигурно', 'дефинитивно', 'рецепт', 'доза'];

    /** @var list<string> */
    private array $errors = [];

    public function test_the_content_package_ships_between_30_and_40_flows(): void
    {
        $count = count($this->contentFiles());

        $this->assertGreaterThanOrEqual(30, $count);
        $this->assertLessThanOrEqual(40, $count);
    }

    public function test_every_flow_file_is_structurally_valid_and_safe(): void
    {
        foreach ($this->contentFiles() as $key => $path) {
            $flow = json_decode((string) file_get_contents($path), true);

            if (! is_array($flow)) {
                $this->errors[] = "$key: not valid JSON";

                continue;
            }

            $this->checkFlow($key, $flow);
        }

        $this->assertSame([], $this->errors, implode("\n", $this->errors));
    }

    public function test_self_harm_red_flags_route_to_the_crisis_outcome_and_never_lower(): void
    {
        $seen = 0;

        foreach ($this->contentFiles() as $key => $path) {
            $flow = json_decode((string) file_get_contents($path), true);

            foreach ($flow['red_flags'] as $flag) {
                if (preg_match('/самоубиств|самоповред|одземете живот|наштетите на бебето|повредите себеси/u', $flag['label']) === 1) {
                    $seen++;
                    $this->assertSame('global:crisis', $flag['outcome'] ?? null, "$key: {$flag['code']}");
                }
            }

            foreach ($flow['outcomes'] as $id => $outcome) {
                if (($outcome['crisis'] ?? false) === true) {
                    $this->assertSame('emergency_now', $outcome['level'], "$key.$id");
                }
            }
        }

        $this->assertGreaterThan(0, $seen);
    }

    public function test_every_outcome_points_to_specialties_from_the_licence_catalogue(): void
    {
        $groups = array_values(array_filter(array_unique(array_column(
            require database_path('seeders/data/licence_specialty_groups.php'),
            'group',
        ))));

        foreach ($this->contentFiles() as $key => $path) {
            $flow = json_decode((string) file_get_contents($path), true);

            foreach ($flow['outcomes'] as $id => $outcome) {
                foreach ($outcome['care']['specialties'] as $specialty) {
                    $this->assertContains($specialty, $groups, "$key.$id");
                }
            }
        }
    }

    /**
     * @return array<string, string> key => path
     */
    private function contentFiles(): array
    {
        $files = [];

        foreach (glob(database_path('data/triage/flows/*.json')) ?: [] as $path) {
            $key = basename($path, '.json');

            if (! in_array($key, self::ENGINE_FILES, true)) {
                $files[$key] = $path;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function checkFlow(string $key, array $flow): void
    {
        $e = function (string $message) use ($key): void {
            $this->errors[] = "$key: $message";
        };

        if (($flow['schema'] ?? null) !== self::SCHEMA) {
            $e('schema');
        }
        if (($flow['key'] ?? null) !== $key || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) !== 1) {
            $e('key must equal the file name');
        }
        if (! is_string($flow['title'] ?? null) || mb_strlen($flow['title']) > 80) {
            $e('title');
        }
        if (! is_string($flow['summary_en'] ?? null) || $flow['summary_en'] === '') {
            $e('summary_en');
        }

        $bands = $flow['audience']['age_bands'] ?? [];
        if ($bands === [] || array_diff($bands, self::AGE_BANDS) !== []) {
            $e('audience.age_bands');
        }
        $rank = $flow['urgency_rank'] ?? null;
        if (! is_int($rank) || $rank < 1 || $rank > 100) {
            $e('urgency_rank');
        }
        if (($flow['body_areas'] ?? []) === [] || array_diff($flow['body_areas'], self::BODY_AREAS) !== []) {
            $e('body_areas');
        }
        $terms = $flow['search_terms'] ?? [];
        if (count($terms) < 3) {
            $e('fewer than 3 search terms');
        }
        foreach ($terms as $term) {
            if (preg_match('/[A-Za-z]/', $term) === 1 || mb_strtolower($term) !== $term) {
                $e("search term must be lower-case Cyrillic: $term");
            }
        }

        // Sources: every citation resolves, public domains only.
        $sources = [];
        foreach ($flow['sources'] ?? [] as $source) {
            $sources[$source['id']] = true;
            $host = (string) parse_url($source['url'] ?? '', PHP_URL_HOST);
            $public = false;
            foreach (self::PUBLIC_DOMAINS as $domain) {
                $public = $public || $host === $domain || str_ends_with($host, '.'.$domain);
            }
            if (! $public || ! str_starts_with($source['url'], 'https://')) {
                $e("source {$source['id']} is not a public https source");
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $source['accessed'] ?? '') !== 1) {
                $e("source {$source['id']} has no access date");
            }
        }
        if ($sources === []) {
            $e('no sources');
        }

        $nodes = $flow['nodes'] ?? [];
        $outcomes = $flow['outcomes'] ?? [];
        if (array_intersect_key($nodes, $outcomes) !== []) {
            $e('node and outcome ids clash');
        }
        foreach (array_merge(array_keys($nodes), array_keys($outcomes)) as $id) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $id) !== 1 || strlen($id) > 48) {
                $e("bad id $id");
            }
        }

        // Red flags: present, cited, demographic-only conditions, emergency outcomes.
        if (($flow['red_flags'] ?? []) === []) {
            $e('no red flags');
        }
        $demoRefs = [];
        foreach ($flow['red_flags'] ?? [] as $flag) {
            if (! isset($sources[$flag['source'] ?? ''])) {
                $e("red flag {$flag['code']} cites an unknown source");
            }
            if (isset($flag['when'])) {
                if (str_contains((string) json_encode($flag['when']), '"answer"')) {
                    $e("red flag {$flag['code']} uses answers");
                }
                $this->checkCondition($key, $flag['when'], $nodes, $demoRefs);
            }
            $target = $flag['outcome'] ?? 'global:emergency_now';
            if (! in_array($target, ['global:emergency_now', 'global:crisis'], true)
                && ($outcomes[$target]['level'] ?? null) !== 'emergency_now') {
                $e("red flag {$flag['code']} must lead to an emergency outcome");
            }
            $this->checkText($key, [$flag['label'], $flag['help'] ?? '']);
        }

        // Nodes and routing.
        $entry = $flow['entry'] ?? null;
        if (! isset($nodes[$entry])) {
            $e('entry is not a node');
        }
        foreach ($nodes as $id => $node) {
            if ($node['type'] === 'question') {
                if (! in_array($node['kind'], self::KINDS, true)) {
                    $e("$id kind");
                }
                if (mb_strlen($node['text']) > 300 || mb_strlen($node['help'] ?? '') > 500) {
                    $e("$id text too long");
                }
                if (in_array($node['kind'], ['single', 'multi'], true)) {
                    $max = $node['kind'] === 'single' ? 12 : 15;
                    if (count($node['options']) < 2 || count($node['options']) > $max) {
                        $e("$id option count");
                    }
                    foreach ($node['options'] as $option) {
                        if (preg_match('/^[a-z0-9][a-z0-9_]*$/', $option['value']) !== 1 || in_array($option['value'], ['unknown', 'seen'], true)) {
                            $e("$id option value {$option['value']}");
                        }
                        if (mb_strlen($option['label']) > 160) {
                            $e("$id option label too long");
                        }
                        $this->checkText($key, [$option['label'], $option['help'] ?? '']);
                    }
                }
                if ($node['kind'] === 'number' && ! in_array($node['unit'] ?? null, self::UNITS, true)) {
                    $e("$id unit");
                }
                $this->checkText($key, [$node['text'], $node['help'] ?? '']);
            } elseif ($node['type'] === 'info') {
                if (mb_strlen($node['title']) > 80 || mb_strlen($node['text']) > 600) {
                    $e("$id info too long");
                }
                $this->checkText($key, [$node['title'], $node['text']]);
            } else {
                $e("$id type");
            }

            $next = $node['next'] ?? null;
            $branches = is_string($next) ? [['goto' => $next]] : (array) $next;
            if ($branches === [] || isset(end($branches)['when'])) {
                $e("$id: the last branch must be the default");
            }
            foreach ($branches as $branch) {
                $goto = $branch['goto'] ?? '';
                if (! isset($nodes[$goto]) && ! isset($outcomes[$goto]) && ! in_array($goto, ['global:emergency_now', 'global:crisis'], true)) {
                    $e("$id goes to unknown $goto");
                }
                if (isset($branch['when'])) {
                    $this->checkCondition($key, $branch['when'], $nodes, $demoRefs);
                }
            }
        }

        $this->checkGraph($key, $flow);

        // Outcomes.
        foreach ($outcomes as $id => $outcome) {
            $level = $outcome['level'] ?? null;
            if (! in_array($level, self::LEVELS, true)) {
                $e("$id level");
            }
            if (mb_strlen($outcome['title'] ?? '') > 90 || mb_strlen($outcome['summary'] ?? '') > 300 || ($outcome['summary'] ?? '') === '') {
                $e("$id title/summary");
            }
            if (($outcome['reasons'] ?? []) === [] || ($outcome['do_now'] ?? []) === []) {
                $e("$id needs reasons and do_now");
            }
            if ($level !== 'emergency_now' && ($outcome['watch_for'] ?? []) === []) {
                $e("$id needs safety-netting (watch_for)");
            }
            if ($level === 'emergency_now') {
                $numbers = array_column($outcome['call'] ?? [], 'number');
                if (! in_array('194', $numbers, true) || ! in_array('112', $numbers, true)) {
                    $e("$id: emergency outcome without 194 and 112");
                }
            }
            if (($outcome['crisis'] ?? false) === true && $level !== 'emergency_now') {
                $e("$id: crisis on a non-emergency outcome");
            }
            if (! in_array($outcome['care']['setting'] ?? null, self::SETTINGS, true)
                || array_diff($outcome['care']['facility_types'] ?? [], self::FACILITY_TYPES) !== []) {
                $e("$id care");
            }
            $texts = array_merge([$outcome['title'], $outcome['summary']], $outcome['reasons'], $outcome['do_now'], $outcome['watch_for']);
            foreach (array_merge($outcome['reasons'], $outcome['do_now'], $outcome['watch_for']) as $item) {
                if (mb_strlen($item) > 200) {
                    $e("$id item too long: ".mb_substr($item, 0, 40));
                }
            }
            foreach ($texts as $text) {
                foreach (self::BANNED_IN_OUTCOMES as $word) {
                    if (str_contains(mb_strtolower($text), $word)) {
                        $e("$id uses banned wording „{$word}“");
                    }
                }
            }
            foreach ($outcome['sources'] ?? [] as $source) {
                if (! isset($sources[$source])) {
                    $e("$id cites unknown source $source");
                }
            }
            $this->checkText($key, $texts);
        }

        $this->checkPopulations($key, $bands, $demoRefs, $flow['populations'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $nodes
     * @param  list<array<string, mixed>>  $demoRefs
     */
    private function checkCondition(string $key, array $condition, array $nodes, array &$demoRefs): void
    {
        foreach (['all', 'any'] as $combinator) {
            if (isset($condition[$combinator])) {
                foreach ($condition[$combinator] as $part) {
                    $this->checkCondition($key, $part, $nodes, $demoRefs);
                }

                return;
            }
        }
        if (isset($condition['not'])) {
            $this->checkCondition($key, $condition['not'], $nodes, $demoRefs);

            return;
        }

        $operators = array_values(array_diff(array_keys($condition), ['answer', 'demo']));
        if (count($operators) !== 1) {
            $this->errors[] = "$key: a condition needs exactly one operator";

            return;
        }
        $op = $operators[0];
        $value = $condition[$op];

        if (isset($condition['demo'])) {
            $field = $condition['demo'];
            if (! array_key_exists($field, self::DEMO_FIELDS)) {
                $this->errors[] = "$key: unknown demographic $field";

                return;
            }
            $allowed = self::DEMO_FIELDS[$field];
            if ($allowed !== null && ! in_array($op, ['between', 'gte', 'lte', 'gt', 'lt'], true)) {
                foreach ((array) $value as $v) {
                    if (! in_array($v, $allowed, true)) {
                        $this->errors[] = "$key: demographic $field has no value $v";
                    }
                }
            }
            $demoRefs[] = $condition;

            return;
        }

        $node = $nodes[$condition['answer'] ?? ''] ?? null;
        if ($node === null || $node['type'] !== 'question') {
            $this->errors[] = "$key: condition on unknown question ".($condition['answer'] ?? '?');

            return;
        }
        $numeric = in_array($op, ['gte', 'gt', 'lte', 'lt', 'between'], true);
        if ($op === 'answered') {
            // Schema §7: whether the question was answered (an optional one may be skipped).
            if (! is_bool($value)) {
                $this->errors[] = "$key: answered on {$condition['answer']} must be true or false";
            }
        } elseif (in_array($node['kind'], ['single', 'multi'], true)) {
            if ($numeric) {
                $this->errors[] = "$key: numeric comparison on {$condition['answer']}";
            }
            $offered = array_column($node['options'], 'value');
            foreach ((array) $value as $v) {
                if (! in_array($v, $offered, true)) {
                    $this->errors[] = "$key: {$condition['answer']} does not offer $v";
                }
            }
        } elseif ($node['kind'] === 'yes_no') {
            foreach ((array) $value as $v) {
                if (! in_array($v, ['yes', 'no', 'unsure'], true)) {
                    $this->errors[] = "$key: yes/no answer $v";
                }
            }
        } elseif (! $numeric && ! ($op === 'eq' && $value === 'unknown')) {
            $this->errors[] = "$key: non-numeric comparison on {$condition['answer']}";
        }
    }

    /**
     * Every node is reachable from the entry, there are no cycles, and so
     * every path ends in an outcome.
     *
     * @param  array<string, mixed>  $flow
     */
    private function checkGraph(string $key, array $flow): void
    {
        $nodes = $flow['nodes'];
        $targets = function (array $node): array {
            return is_string($node['next']) ? [$node['next']] : array_column($node['next'], 'goto');
        };

        $reached = [];
        foreach ($flow['red_flags'] as $flag) {
            $reached[$flag['outcome'] ?? 'global:emergency_now'] = true;
        }
        $state = [];
        $visit = function (string $id) use (&$visit, &$state, &$reached, $nodes, $targets, $key): void {
            $reached[$id] = true;
            if (! isset($nodes[$id])) {
                return;
            }
            if (($state[$id] ?? 0) === 1) {
                $this->errors[] = "$key: cycle through $id";

                return;
            }
            if (($state[$id] ?? 0) === 2) {
                return;
            }
            $state[$id] = 1;
            foreach ($targets($nodes[$id]) as $target) {
                $visit($target);
            }
            $state[$id] = 2;
        };
        $visit($flow['entry']);

        foreach (array_merge(array_keys($nodes), array_keys($flow['outcomes'])) as $id) {
            if (! isset($reached[$id])) {
                $this->errors[] = "$key: $id is unreachable";
            }
        }
    }

    /**
     * @param  list<string>  $bands
     * @param  list<array<string, mixed>>  $demoRefs
     * @param  array<string, string>  $notes
     */
    private function checkPopulations(string $key, array $bands, array $demoRefs, array $notes): void
    {
        $refs = (string) json_encode($demoRefs);
        $mentions = fn (array $needles): bool => array_filter($needles, fn (string $n): bool => str_contains($refs, $n)) !== [];

        $relevant = ['chronic' => $mentions(['"conditions"'])];
        if (in_array('infant_0_3m', $bands, true)) {
            $relevant['infant_0_3m'] = $mentions(['infant_0_3m', 'age_months']);
        }
        if (in_array('infant_3_12m', $bands, true)) {
            $relevant['infant_3_12m'] = $mentions(['infant_3_12m', 'age_months']);
        }
        if (array_intersect($bands, ['child_1_4', 'child_5_12', 'teen_13_17']) !== []) {
            $relevant['child'] = $mentions(['child_1_4', 'child_5_12', 'teen_13_17', 'age_years', 'age_months']);
        }
        if (array_intersect($bands, ['teen_13_17', 'adult_18_64']) !== []) {
            $relevant['pregnancy'] = $mentions(['"pregnancy"']);
        }
        if (in_array('older_65_plus', $bands, true)) {
            $relevant['older_adult'] = $mentions(['older_65_plus', 'age_years']);
        }

        foreach ($relevant as $population => $referenced) {
            if (! $referenced && trim($notes[$population] ?? '') === '') {
                $this->errors[] = "$key: population $population is neither referenced nor explained";
            }
        }
    }

    /**
     * @param  list<string>  $texts
     */
    private function checkText(string $key, array $texts): void
    {
        foreach ($texts as $text) {
            if (preg_match(self::FOREIGN_LETTERS, $text) === 1) {
                $this->errors[] = "$key: non-Macedonian letter in „{$text}“";
            }
            if (preg_match('/\p{Cyrillic}[A-Za-z]|[A-Za-z]\p{Cyrillic}/u', $text) === 1) {
                $this->errors[] = "$key: Latin letter inside a Cyrillic word in „{$text}“";
            }
        }
    }
}
