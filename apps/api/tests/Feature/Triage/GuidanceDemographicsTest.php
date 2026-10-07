<?php

namespace Tests\Feature\Triage;

use App\Services\Triage\V2\AnswerNormalizer;
use App\Services\Triage\V2\ConditionEvaluator;
use App\Services\Triage\V2\Demographics;
use App\Services\Triage\V2\FlowImporter;
use App\Services\Triage\V2\FlowWalker;
use App\Services\Triage\V2\GlobalScreen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The shipped flows must not ask, show or advise things that do not fit the
 * visitor's sex, age or pregnancy status (a female was once asked about
 * testicular pain). Checked at run time against the real walker and the real
 * red-flag screen, independently of the linter rule.
 */
class GuidanceDemographicsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/triage/v2';

    /** Word stems that name something only some visitors can have. */
    private const MALE_ONLY = ['тестис', 'тестикул', 'скротум', 'простат', 'пенис', 'ерекци'];

    private const FEMALE_ONLY = ['менструа', 'вагин', 'матка', 'јајник', 'менопауз'];

    private const PREGNANCY = ['бремен', 'породув', 'постпартум'];

    private const INFANT_ONLY = ['фонтанел', 'пелен', 'доенч', 'новороденч'];

    private const ADULT_ONLY = ['алкохол', 'возење', 'менопауз', 'простат', 'ерекци'];

    private string $token = '';

    private string $id = '';

    /** @return array<string, mixed> */
    private static function flow(string $key): array
    {
        return json_decode((string) file_get_contents(database_path("data/triage/flows/{$key}.json")), true);
    }

    /** @return array<string, array<string, mixed>> */
    private static function allFlows(): array
    {
        $flows = [];

        foreach (glob(database_path('data/triage/flows/*.json')) ?: [] as $path) {
            $flows[basename($path, '.json')] = json_decode((string) file_get_contents($path), true);
        }

        return $flows;
    }

    private static function demo(int $months, string $sex, ?string $pregnancy = null): Demographics
    {
        $years = intdiv($months, 12);

        return new Demographics(
            $months,
            $sex,
            Demographics::pregnancyIsAsked($sex, $years) ? ($pregnancy ?? 'not_pregnant') : 'not_asked',
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function textOf(array $item): string
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

        return mb_strtolower(implode("\n", $parts));
    }

    /**
     * The stems this visitor must never meet.
     *
     * @return array<string, list<string>> label => stems
     */
    private static function forbiddenFor(Demographics $demo, bool $sexWaived): array
    {
        $forbidden = [];

        if (! $sexWaived) {
            if ($demo->sex === 'female') {
                $forbidden['male-only'] = self::MALE_ONLY;
            }

            if ($demo->sex === 'male') {
                $forbidden['female-only'] = self::FEMALE_ONLY;
            }

            if ($demo->sex === 'male' || $demo->pregnancy === 'not_asked') {
                $forbidden['pregnancy'] = self::PREGNANCY;
            }
        }

        if ($demo->ageYears() >= 5) {
            $forbidden['infant-only'] = self::INFANT_ONLY;
        }

        if ($demo->ageYears() < 13) {
            $forbidden['adult-only'] = self::ADULT_ONLY;
        }

        return $forbidden;
    }

    /**
     * Random but reproducible answer for a question.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private static function randomAnswer(array $node): array
    {
        if (($node['type'] ?? null) === 'info') {
            return [AnswerNormalizer::INFO_SEEN];
        }

        $options = (array) ($node['options'] ?? []);

        return match ($node['kind']) {
            'single' => [(string) $options[mt_rand(0, count($options) - 1)]['value']],
            'multi' => self::randomMulti($options),
            'yes_no' => [['yes', 'no', 'no', ($node['allow_unsure'] ?? false) ? 'unsure' : 'yes'][mt_rand(0, 3)]],
            'number' => [(string) array_values(array_filter([$node['min'], $node['max'], ($node['min'] + $node['max']) / 2, ($node['allow_unknown'] ?? false) ? 'unknown' : null], fn ($v) => $v !== null))[mt_rand(0, ($node['allow_unknown'] ?? false) ? 3 : 2)]],
            default => [(string) mt_rand(0, 10)],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<string>
     */
    private static function randomMulti(array $options): array
    {
        $exclusive = array_values(array_filter($options, fn ($o) => $o['exclusive'] ?? false));

        if ($exclusive !== [] && mt_rand(0, 3) === 0) {
            return [(string) $exclusive[0]['value']];
        }

        $picked = [];

        foreach ($options as $option) {
            if (! ($option['exclusive'] ?? false) && mt_rand(0, 2) === 0) {
                $picked[] = (string) $option['value'];
            }
        }

        return $picked === [] ? [(string) $options[0]['value']] : $picked;
    }

    /**
     * Everything the visitor can be shown in a flow: the red-flag screen plus
     * many random walks (nodes asked, outcomes reached).
     *
     * @param  array<string, mixed>  $flow
     * @return array<string, array<string, mixed>> location => item
     */
    private function shownTo(array $flow, Demographics $demo, GlobalScreen $global, int $walks = 60): array
    {
        $values = $demo->toConditionValues();
        $evaluator = new ConditionEvaluator($values, []);
        $shown = [];

        foreach ($global->redFlags as $flag) {
            if (! array_key_exists('when', $flag) || $evaluator->evaluate($flag['when'])) {
                $shown['global red flag '.$flag['code']] = $flag;
            }
        }

        foreach ($flow['red_flags'] as $flag) {
            if (! array_key_exists('when', $flag) || $evaluator->evaluate($flag['when'])) {
                $shown[$flow['key'].' red flag '.$flag['code']] = $flag;
                $outcome = $flag['outcome'] ?? null;

                if (is_string($outcome) && isset($flow['outcomes'][$outcome])) {
                    $shown[$flow['key'].' outcome '.$outcome] = $flow['outcomes'][$outcome];
                }
            }
        }

        $walker = new FlowWalker;
        mt_srand(crc32($flow['key'].$demo->ageMonths.$demo->sex.$demo->pregnancy));

        for ($i = 0; $i < $walks; $i++) {
            $answers = [];

            for ($step = 0; $step < 60; $step++) {
                $result = $walker->walk($flow, $values, $answers);

                if ($result->finished()) {
                    if (isset($flow['outcomes'][$result->outcome])) {
                        $shown[$flow['key'].' outcome '.$result->outcome] = $flow['outcomes'][$result->outcome];
                    }

                    break;
                }

                $this->assertNotNull($result->current, "{$flow['key']}: the walk broke");
                $node = $flow['nodes'][$result->current];
                $shown[$flow['key'].' question '.$result->current] = $node;
                $answers[$result->current] = self::randomAnswer($node);
            }
        }

        return $shown;
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function visitors(): array
    {
        return [
            'girl 14' => [14 * 12, 'female'],
            'woman 30' => [30 * 12, 'female'],
            'woman 58' => [58 * 12, 'female'],
            'woman 70' => [70 * 12, 'female'],
            'boy 14' => [14 * 12, 'male'],
            'man 30' => [30 * 12, 'male'],
            'man 70' => [70 * 12, 'male'],
            'unspecified 30' => [30 * 12, 'unspecified'],
            'unspecified 70' => [70 * 12, 'unspecified'],
            'newborn girl' => [1, 'female'],
            'baby boy 6 months' => [6, 'male'],
            'toddler girl 2' => [24, 'female'],
            'child boy 8' => [96, 'male'],
            'child girl 11' => [132, 'female'],
        ];
    }

    #[DataProvider('visitors')]
    public function test_no_flow_shows_a_visitor_what_does_not_apply_to_them(int $months, string $sex): void
    {
        $global = GlobalScreen::load();
        $demo = self::demo($months, $sex);
        $problems = [];

        foreach (self::allFlows() as $key => $flow) {
            if (! in_array($demo->ageBand(), $flow['audience']['age_bands'], true)) {
                continue;
            }

            $forbidden = self::forbiddenFor($demo, isset($flow['demographics_ok_reason']));

            foreach ($this->shownTo($flow, $demo, $global) as $where => $item) {
                if (isset($item['demographics_ok_reason'])) {
                    continue;
                }

                $text = self::textOf($item);

                foreach ($forbidden as $label => $stems) {
                    foreach ($stems as $stem) {
                        if (str_contains($text, $stem)) {
                            $problems[$where.' / '.$label] = $stem;
                        }
                    }
                }
            }
        }

        $this->assertSame([], $problems, "Shown to {$sex}, {$months} months:\n".json_encode($problems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function test_every_pregnancy_status_of_a_woman_sees_pregnancy_red_flags_only_when_it_applies(): void
    {
        $global = GlobalScreen::load();

        foreach (['pregnant', 'unsure', 'postpartum'] as $status) {
            $evaluator = new ConditionEvaluator(self::demo(30 * 12, 'female', $status)->toConditionValues(), []);
            $codes = array_column(array_filter($global->redFlags, fn ($f) => ! isset($f['when']) || $evaluator->evaluate($f['when'])), 'code');
            $this->assertContains('pregnancy_preeclampsia', $codes, $status);
        }

        foreach ([self::demo(30 * 12, 'female', 'not_pregnant'), self::demo(30 * 12, 'male'), self::demo(70 * 12, 'female')] as $demo) {
            $evaluator = new ConditionEvaluator($demo->toConditionValues(), []);
            $codes = array_column(array_filter($global->redFlags, fn ($f) => ! isset($f['when']) || $evaluator->evaluate($f['when'])), 'code');
            $this->assertNotContains('pregnancy_preeclampsia', $codes, "{$demo->sex} {$demo->pregnancy}");
        }
    }

    // ---------------------------------------------------------------- through the API

    private function open(): void
    {
        $response = $this->postJson(self::BASE.'/sessions', ['accepted_terms' => true])->assertCreated();
        $this->id = $response->json('data.session_id');
        $this->token = $response->json('data.session_token');
    }

    private function guide(string $method, string $path, array $data = []): TestResponse
    {
        return $this->json($method, self::BASE.'/sessions/'.$this->id.$path, $data, ['X-Guidance-Token' => $this->token]);
    }

    /** @return list<string> labels of the red-flag screen */
    private function screenLabels(int $age, string $sex, array $flows): array
    {
        $this->open();
        $this->guide('PUT', '/demographics', [
            'age_value' => $age, 'age_unit' => 'years', 'sex' => $sex,
            'pregnancy' => Demographics::pregnancyIsAsked($sex, $age) ? 'not_pregnant' : null, 'conditions' => [],
        ])->assertOk();

        return array_column($this->guide('PUT', '/symptoms', ['flows' => $flows])->assertOk()->json('data.screen'), 'label');
    }

    private function publishShippedFlows(): void
    {
        config(['triage.preview_drafts' => true]);
        app(FlowImporter::class)->import();
    }

    public function test_the_red_flag_screen_respects_sex_through_the_api(): void
    {
        $this->publishShippedFlows();
        $flows = ['abdominal-pain', 'urinary-symptoms'];

        $female = implode("\n", $this->screenLabels(30, 'female', $flows));
        $this->assertStringNotContainsString('тестис', $female);
        $this->assertStringContainsString('бременост', $female, 'A woman of child-bearing age still gets the ectopic-pregnancy red flag.');

        $male = implode("\n", $this->screenLabels(30, 'male', $flows));
        $this->assertStringContainsString('тестис', $male);
        $this->assertStringNotContainsString('бременост', $male);

        // Sex not given: sex-specific red flags are kept (under-triage is worse), worded neutrally.
        $unspecified = implode("\n", $this->screenLabels(30, 'unspecified', $flows));
        $this->assertStringContainsString('тестис', $unspecified);
        $this->assertStringContainsString('бременост', $unspecified);
        $this->assertStringNotContainsString('кај мажи', $unspecified);

        // A woman past child-bearing age is not asked about a possible pregnancy.
        $this->assertStringNotContainsString('бременост', implode("\n", $this->screenLabels(70, 'female', $flows)));
    }

    public function test_the_screen_of_a_child_has_no_adult_only_items_and_an_older_child_no_nappies(): void
    {
        $this->publishShippedFlows();

        $baby = implode("\n", $this->screenLabels(1, 'female', ['fever-infant-child']));
        $this->assertStringContainsString('пелени', $baby);

        $child = implode("\n", $this->screenLabels(8, 'female', ['fever-infant-child', 'vomiting-diarrhoea-child']));
        $this->assertStringNotContainsString('пелен', $child);
        $this->assertStringContainsString('Не мокрело 12 часа', $child, 'The red flag stays, worded for the age.');
    }
}
