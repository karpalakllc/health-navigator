<?php

namespace Tests\Feature\Triage;

use App\Services\Triage\V2\FlowImporter;
use App\Services\Triage\V2\FlowLinter;
use App\Services\Triage\V2\GlobalScreen;
use App\Services\Triage\V2\LintReport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlowLinterTest extends TestCase
{
    public static function example(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/triage/example-sore-throat.json'), true);
    }

    private function lint(array $flow): LintReport
    {
        return (new FlowLinter)->lint($flow, GlobalScreen::load(), 'example-sore-throat');
    }

    public function test_the_schema_example_and_every_shipped_file_pass(): void
    {
        $this->assertSame([], $this->lint(self::example())->errors);

        $global = GlobalScreen::load();
        $globalReport = (new FlowLinter)->lintGlobal($global);
        $this->assertSame([], $globalReport->errors);

        foreach (FlowImporter::flowFiles() as $path) {
            $flow = json_decode((string) file_get_contents($path), true);
            $this->assertIsArray($flow, basename($path));
            $this->assertSame([], (new FlowLinter)->lint($flow, $global, basename($path, '.json'))->errors, basename($path));
        }
    }

    /**
     * Each mutation breaks one rule of docs/triage-flows.md §13; the linter
     * must name it.
     */
    #[DataProvider('mutations')]
    public function test_it_rejects(callable $mutate, string $expected): void
    {
        $flow = self::example();
        $mutate($flow);

        $errors = implode("\n", $this->lint($flow)->errors);

        $this->assertStringContainsString($expected, $errors);
    }

    public static function mutations(): array
    {
        return [
            'no red flags' => [function (&$f) {
                $f['red_flags'] = [];
            }, 'red_flags: every flow asks its own red flags first'],
            'red flag without a source' => [function (&$f) {
                unset($f['red_flags'][0]['source']);
            }, 'red_flags[0].source: every red flag cites a source'],
            'red flag reading an answer' => [function (&$f) {
                $f['red_flags'][0]['when'] = ['answer' => 'q_fever', 'eq' => 'yes'];
            }, 'only demographics ("demo") can be used here'],
            'red flag to a non-emergency outcome' => [function (&$f) {
                $f['red_flags'][0]['outcome'] = 'o_pharmacy';
            }, 'a red flag must lead to an emergency_now outcome'],
            'a cycle' => [function (&$f) {
                $f['nodes']['q_fever']['next'] = 'q_duration';
                $f['nodes']['q_duration']['next'] = 'q_fever';
            }, 'is part of a cycle'],
            'an unreachable node' => [function (&$f) {
                $f['nodes']['q_orphan'] = ['type' => 'question', 'kind' => 'yes_no', 'text' => 'Дали?', 'next' => 'o_self_care'];
            }, 'nodes.q_orphan: is unreachable from the entry'],
            'a dead end (no default branch)' => [function (&$f) {
                array_pop($f['nodes']['q_pain']['next']);
            }, 'the last branch is the default and must not have "when"'],
            'an unknown target' => [function (&$f) {
                $f['nodes']['q_fever']['next'] = 'q_missing';
            }, 'unknown target "q_missing"'],
            'emergency outcome without 112' => [function (&$f) {
                $f['outcomes']['o_er'] = [
                    'level' => 'emergency_now', 'title' => 'Јавете се на 194', 'summary' => 'Итно.',
                    'reasons' => ['Причина.'], 'do_now' => ['Јавете се.'], 'watch_for' => [],
                    'call' => [['number' => '194', 'label' => 'Итна помош']],
                    'care' => ['setting' => 'emergency_department', 'specialties' => [], 'facility_types' => []],
                ];
                $f['nodes']['q_pain']['next'][0]['goto'] = 'o_er';
                $f['outcomes']['o_same_day']['level'] = 'urgent_same_day';
                $f['nodes']['q_fever']['next'] = [['when' => ['answer' => 'q_fever', 'eq' => 'unsure'], 'goto' => 'o_same_day'], ['goto' => 'q_pain']];
            }, 'an emergency outcome must offer tel links to 194 and 112'],
            'outcome without safety-netting' => [function (&$f) {
                $f['outcomes']['o_pharmacy']['watch_for'] = [];
            }, 'outcomes.o_pharmacy.watch_for: safety-netting is required'],
            'crisis on a non-emergency outcome' => [function (&$f) {
                $f['outcomes']['o_pharmacy']['crisis'] = true;
            }, 'only an emergency_now outcome can be a crisis outcome'],
            'a population neither handled nor explained' => [function (&$f) {
                unset($f['populations']['pregnancy']);
            }, '"pregnancy" is in the audience but no condition handles it'],
            'infants in the audience without infant handling' => [function (&$f) {
                $f['audience']['age_bands'][] = 'infant_0_3m';
            }, '"infant_0_3m" is in the audience'],
            'an unknown specialty' => [function (&$f) {
                $f['outcomes']['o_gp_soon']['care']['specialties'] = ['kardio'];
            }, 'unknown specialty key "kardio"'],
            'letters outside the Macedonian alphabet' => [function (&$f) {
                $f['nodes']['q_fever']['text'] = 'Дали имате температура? (й)';
            }, 'contains letters outside the Macedonian alphabet'],
            'a condition on a later answer' => [function (&$f) {
                $f['nodes']['q_fever']['next'] = [['when' => ['answer' => 'q_pain', 'gte' => 5], 'goto' => 'o_same_day'], ['goto' => 'q_pain']];
            }, '"q_pain" is never answered before this point'],
            'a numeric operator on a yes/no answer' => [function (&$f) {
                $f['nodes']['q_pain']['next'][3]['when'] = ['answer' => 'q_fever', 'gte' => 1];
            }, 'numeric operator on a yes_no question'],
            'an answer the question cannot have' => [function (&$f) {
                $f['nodes']['q_pain']['next'][3]['when'] = ['answer' => 'q_fever', 'in' => ['maybe']];
            }, '"maybe" is not an answer that question can have'],
            'no sources' => [function (&$f) {
                $f['sources'] = [];
            }, 'every flow cites at least one public source'],
            'a key that is not the file name' => [function (&$f) {
                $f['key'] = 'another-key';
            }, 'must equal the file name'],
        ];
    }

    public function test_it_warns_about_soft_problems(): void
    {
        $flow = self::example();
        $flow['nodes']['q_duration']['next'] = 'q_fever';
        $flow['sources'][0]['url'] = 'https://example.com/sore-throat';
        $flow['red_flags'][] = ['code' => 'dup', 'label' => 'Обилно крварење што не престанува', 'source' => 'nhs-sore-throat'];

        $report = $this->lint($flow);
        $warnings = implode("\n", $report->warnings);

        $this->assertSame([], $report->errors);
        $this->assertStringContainsString('allows "unknown" but no branch routes it explicitly', $warnings);
        $this->assertStringContainsString('is not on the list of public sources', $warnings);
        $this->assertStringContainsString('repeats a global red flag', $warnings);
    }

    public function test_the_global_screen_must_define_emergency_and_crisis_outcomes(): void
    {
        $global = GlobalScreen::fromFiles(['core.json' => [
            'schema' => GlobalScreen::SCHEMA,
            'red_flags' => [['code' => 'x', 'label' => 'Нешто итно', 'source' => 's']],
            'outcomes' => [],
            'sources' => [['id' => 's', 'title' => 'NHS', 'url' => 'https://www.nhs.uk/', 'accessed' => '2026-10-07']],
        ]]);

        $errors = implode("\n", (new FlowLinter)->lintGlobal($global)->errors);

        $this->assertStringContainsString('outcome "emergency_now" is required', $errors);
        $this->assertStringContainsString('outcome "crisis" is required', $errors);
    }

    public function test_lint_command_fails_on_a_broken_file(): void
    {
        $flow = self::example();
        unset($flow['red_flags'][0]['source']);
        $path = sys_get_temp_dir().'/example-sore-throat.json';
        file_put_contents($path, json_encode($flow));

        $this->artisan('triage:lint', ['paths' => [$path]])
            ->expectsOutputToContain('every red flag cites a source')
            ->assertFailed();

        $this->artisan('triage:lint', ['paths' => [__DIR__.'/../../Fixtures/triage/example-sore-throat.json']])
            ->assertSuccessful();

        @unlink($path);
    }
}
