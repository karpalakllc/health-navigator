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

    /**
     * Inserts a yes/no question between q_duration and q_fever; reached
     * through $guard when given, otherwise by everyone.
     *
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>|null  $guard
     * @return array<string, mixed>
     */
    private static function withQuestion(array $flow, string $text, ?array $guard = null): array
    {
        $flow['nodes']['q_extra'] = ['type' => 'question', 'kind' => 'yes_no', 'text' => $text, 'next' => 'q_fever'];
        $flow['nodes']['q_duration']['next'] = $guard === null
            ? [['goto' => 'q_extra']]
            : [['when' => $guard, 'goto' => 'q_extra'], ['goto' => 'q_fever']];

        return $flow;
    }

    /** @return array<string, array{string, array<string, mixed>|null, bool}> */
    public static function demographicCases(): array
    {
        $male = ['demo' => 'sex', 'eq' => 'male'];

        return [
            'testicle question for everyone' => ['Дали ве боли тестисот?', null, false],
            'testicle question guarded by male sex' => ['Дали ве боли тестисот?', $male, true],
            'testicle question guarded by male or unspecified' => ['Дали ве боли тестисот?', ['demo' => 'sex', 'in' => ['male', 'unspecified']], true],
            'testicle question guarded by something else' => ['Дали ве боли тестисот?', ['demo' => 'age_band', 'eq' => 'adult_18_64'], false],
            'testicle question guarded by female sex' => ['Дали ве боли тестисот?', ['demo' => 'sex', 'eq' => 'female'], false],
            'any: a sex guard that can be bypassed' => ['Дали ве боли тестисот?', ['any' => [$male, ['demo' => 'age_band', 'eq' => 'adult_18_64']]], false],
            'all: a sex guard with another condition' => ['Дали ве боли тестисот?', ['all' => [$male, ['demo' => 'age_band', 'eq' => 'adult_18_64']]], true],
            'upper case, other ending' => ['Силна болка во ТЕСТИСИТЕ?', null, false],
            'menstruation question for everyone' => ['Дали менструацијата ви доцни?', null, false],
            'menstruation question guarded by female' => ['Дали менструацијата ви доцни?', ['demo' => 'sex', 'in' => ['female', 'unspecified']], true],
            'pregnancy question for everyone' => ['Дали сте бремени?', null, false],
            'pregnancy question only where pregnancy is asked' => ['Дали сте бремени?', ['demo' => 'pregnancy', 'ne' => 'not_asked'], true],
            'pregnancy question guarded by sex alone (older women are not asked)' => ['Дали сте бремени?', ['demo' => 'sex', 'in' => ['female', 'unspecified']], false],
            'prostate question for everyone' => ['Имате ли проблеми со простатата?', null, false],
            'alcohol question for a teenager is fine' => ['Дали пиете алкохол?', null, true],
            'neutral question' => ['Дали имате болка?', null, true],
        ];
    }

    /** @param  array<string, mixed>|null  $guard */
    #[DataProvider('demographicCases')]
    public function test_copy_naming_sex_or_pregnancy_needs_a_demographic_guard(string $text, ?array $guard, bool $passes): void
    {
        $report = $this->lint(self::withQuestion(self::example(), $text, $guard));

        if ($passes) {
            $this->assertSame([], $report->errors);
        } else {
            $this->assertStringContainsString('demographics_ok_reason', implode("\n", $report->errors));
            $this->assertStringContainsString('nodes.q_extra', implode("\n", $report->errors));
        }
    }

    public function test_infant_and_adult_items_need_an_age_guard_when_the_flow_covers_other_ages(): void
    {
        $flow = self::example();
        $flow['audience']['age_bands'] = ['child_5_12', 'teen_13_17', 'adult_18_64'];
        $flow['populations']['child'] = 'Same questions.';

        // Nappies for a 5-12 year old.
        $errors = implode("\n", $this->lint(self::withQuestion($flow, 'Дали има суви пелени?'))->errors);
        $this->assertStringContainsString('infant_care', $errors);

        // Alcohol asked of a child.
        $flow['audience']['age_bands'] = ['infant_3_12m', 'child_1_4', 'child_5_12'];
        $errors = implode("\n", $this->lint(self::withQuestion($flow, 'Дали пиете алкохол?'))->errors);
        $this->assertStringContainsString('adult_activities', $errors);

        // Nappies guarded by age: fine.
        $guard = ['demo' => 'age_years', 'lt' => 5];
        $this->assertStringNotContainsString('infant_care', implode("\n", $this->lint(self::withQuestion($flow, 'Дали има суви пелени?', $guard))->errors));

        // A flow for babies only may talk about nappies.
        $flow['audience']['age_bands'] = ['infant_0_3m', 'infant_3_12m'];
        $this->assertStringNotContainsString('infant_care', implode("\n", $this->lint(self::withQuestion($flow, 'Дали има суви пелени?'))->errors));
    }

    public function test_help_red_flags_and_outcomes_are_checked_too(): void
    {
        $flow = self::example();
        $flow['nodes']['q_fever']['help'] = 'Ако сте бремени, измерете двапати.';
        $flow['red_flags'][] = ['code' => 'testis', 'label' => 'Ненадејна болка во тестисот', 'source' => 'nhs-sore-throat'];
        $flow['outcomes']['o_pharmacy']['do_now'][] = 'Ако менструацијата доцни, направете тест.';

        $errors = implode("\n", $this->lint($flow)->errors);

        $this->assertStringContainsString('nodes.q_fever: mentions „бремен“', $errors);
        $this->assertStringContainsString('red_flags[2]: mentions „тестис“', $errors);
        $this->assertStringContainsString('outcomes.o_pharmacy: mentions „менструа“', $errors);

        // Unspecified sex keeps the sex-specific red flag; a woman does not see it.
        $flow = self::example();
        $flow['red_flags'][] = ['code' => 'testis', 'label' => 'Ненадејна болка во тестисот', 'when' => ['demo' => 'sex', 'in' => ['male', 'unspecified']], 'source' => 'nhs-sore-throat'];
        $this->assertSame([], $this->lint($flow)->errors);
    }

    public function test_a_question_reached_only_through_a_guarded_answer_is_guarded(): void
    {
        $routing = [
            ['when' => ['answer' => 'q_extra', 'eq' => 'yes'], 'goto' => 'o_testis'],
            ['goto' => 'q_pain'],
        ];

        // q_extra is asked of men only; for others the branch on its answer is false.
        $flow = self::withQuestion(self::example(), 'Дали ве боли тестисот?', ['demo' => 'sex', 'eq' => 'male']);
        $flow['nodes']['q_fever']['next'] = $routing;
        $flow['outcomes']['o_testis'] = ['reasons' => ['Болка во тестисот бара преглед денес.']] + $flow['outcomes']['o_same_day'];
        $this->assertSame([], $this->lint($flow)->errors);

        // Asked of everyone, the outcome is reachable by everyone.
        $flow = self::withQuestion(self::example(), 'Дали ве боли?');
        $flow['nodes']['q_fever']['next'] = $routing;
        $flow['outcomes']['o_testis'] = ['reasons' => ['Болка во тестисот бара преглед денес.']] + $flow['outcomes']['o_same_day'];
        $this->assertStringContainsString('outcomes.o_testis: mentions „тестис“', implode("\n", $this->lint($flow)->errors));
    }

    public function test_a_reason_waives_a_node_an_outcome_a_red_flag_or_a_sex_specific_flow(): void
    {
        $flow = self::withQuestion(self::example(), 'Дали ве боли тестисот?');
        $flow['nodes']['q_extra']['demographics_ok_reason'] = 'Asked neutrally for everyone on purpose.';
        $this->assertSame([], $this->lint($flow)->errors);

        $flow = self::example();
        $flow['red_flags'][] = ['code' => 'testis', 'label' => 'Ненадејна болка во тестисот', 'source' => 'nhs-sore-throat', 'demographics_ok_reason' => 'Neutral red flag for unknown sex.'];
        $flow['outcomes']['o_pharmacy']['do_now'][] = 'Ако менструацијата доцни, направете тест.';
        $flow['outcomes']['o_pharmacy']['demographics_ok_reason'] = 'Carer advice, not about the patient.';
        $this->assertSame([], $this->lint($flow)->errors);

        // A flow about one sex waives sex and pregnancy wording, not the age rules.
        $flow = self::withQuestion(self::example(), 'Дали сте бремени?');
        $flow['demographics_ok_reason'] = 'Inherently about pregnancy.';
        $this->assertSame([], $this->lint($flow)->errors);
        $flow['audience']['age_bands'] = ['child_5_12', 'teen_13_17'];
        $flow['populations']['child'] = 'Same.';
        $flow = self::withQuestion($flow, 'Дали има суви пелени?');
        $this->assertStringContainsString('infant_care', implode("\n", $this->lint($flow)->errors));

        // The reason has to be a sentence.
        $flow = self::example();
        $flow['nodes']['q_fever']['demographics_ok_reason'] = 'ok';
        $this->assertStringContainsString('demographics_ok_reason must be a sentence', implode("\n", $this->lint($flow)->errors));
    }

    public function test_the_keyword_list_is_configurable(): void
    {
        $flow = self::withQuestion(self::example(), 'Дали имате брадавици на раката?');
        $this->assertSame([], $this->lint($flow)->errors);

        config(['triage.demographic_keywords' => ['wart' => ['stems' => ['брадавиц'], 'forbidden' => ['age_years_lt' => 18]]]]);

        $this->assertStringContainsString('(wart:', implode("\n", $this->lint($flow)->errors));
    }

    public function test_global_red_flags_are_checked_too(): void
    {
        $global = GlobalScreen::fromFiles(['core.json' => [
            'schema' => GlobalScreen::SCHEMA,
            'red_flags' => [
                ['code' => 'a', 'label' => 'Ненадејна болка во тестисот', 'source' => 's'],
                ['code' => 'b', 'label' => 'Бременост со силно крварење', 'when' => ['demo' => 'pregnancy', 'in' => ['pregnant', 'unsure']], 'source' => 's'],
            ],
            'outcomes' => [],
            'sources' => [['id' => 's', 'title' => 'NHS', 'url' => 'https://www.nhs.uk/', 'accessed' => '2026-10-07']],
        ]]);

        $errors = implode("\n", (new FlowLinter)->lintGlobal($global)->errors);

        $this->assertStringContainsString('global.red_flags[0]: mentions „тестис“', $errors);
        $this->assertStringNotContainsString('global.red_flags[1]: mentions', $errors);
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
