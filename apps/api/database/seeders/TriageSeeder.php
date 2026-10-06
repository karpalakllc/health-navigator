<?php

namespace Database\Seeders;

use App\Enums\TriageStepType;
use App\Models\TriageFlow;
use App\Models\TriageOutcome;
use App\Models\TriageRedFlag;
use App\Models\TriageRule;
use App\Models\TriageStep;
use App\Models\TriageStepOption;
use App\Services\Triage\TriageSessionService;
use Illuminate\Database\Seeder;

class TriageSeeder extends Seeder
{
    public const TITLE = 'Општи насоки за симптоми';

    /** The flow's title before the copy was translated; re-seeding updates it in place. */
    private const LEGACY_TITLE = 'General symptom guidance';

    /**
     * Public copy is Macedonian only: the triage tables hold one string per
     * field, with no locale variant. The strings are listed for native review
     * in docs/mk-copy-review.md (section H).
     */
    public function run(): void
    {
        $flow = TriageFlow::query()
            ->whereIn('title', [self::TITLE, self::LEGACY_TITLE])
            ->first() ?? new TriageFlow;

        $flow->fill([
            'title' => self::TITLE,
            'intro_body' => 'Одговорете на неколку општи прашања за да видите информативни следни чекори. Ова не е медицински совет и не може да поставува дијагнози.',
            'is_published' => true,
        ])->save();

        $this->seedRedFlags($flow);
        $this->seedSteps($flow);
        $this->seedOutcomes($flow);
        $this->seedRules($flow);
    }

    private function seedRedFlags(TriageFlow $flow): void
    {
        $flags = [
            ['code' => 'chest_pain', 'label' => 'Силна болка или притисок во градите'],
            ['code' => 'breathing', 'label' => 'Сериозно отежнато дишење'],
            ['code' => 'bleeding', 'label' => 'Обилно крварење што не престанува'],
            ['code' => 'confusion', 'label' => 'Ненадејна збунетост или неможност да се разбуди'],
            ['code' => 'self_harm', 'label' => 'Мисли за самоповредување или самоубиство'],
        ];

        foreach ($flags as $index => $flag) {
            TriageRedFlag::query()->updateOrCreate(
                ['triage_flow_id' => $flow->id, 'code' => $flag['code']],
                ['label' => $flag['label'], 'sort_order' => $index],
            );
        }
    }

    private function seedSteps(TriageFlow $flow): void
    {
        $steps = [
            [
                'step_key' => 'age_band',
                'type' => TriageStepType::SingleSelect,
                'label' => 'Возрасна група',
                'options' => [
                    ['value' => 'under_18', 'label' => 'Помлади од 18 години'],
                    ['value' => '18_64', 'label' => '18–64 години'],
                    ['value' => 'over_64', 'label' => '65 години или постари'],
                ],
            ],
            [
                'step_key' => 'concern',
                'type' => TriageStepType::SingleSelect,
                'label' => 'Што најдобро го опишува она што ве загрижува?',
                'options' => [
                    ['value' => 'general', 'label' => 'Општи симптоми (болка, температура, замор)'],
                    ['value' => 'injury', 'label' => 'Повреда или незгода'],
                    ['value' => 'wellbeing', 'label' => 'Стрес или психичка благосостојба'],
                ],
            ],
            [
                'step_key' => 'severity',
                'type' => TriageStepType::SingleSelect,
                'label' => 'Колку се изразени симптомите денес?',
                'options' => [
                    ['value' => 'mild', 'label' => 'Благи — се забележуваат, но се поднесливи'],
                    ['value' => 'moderate', 'label' => 'Умерени — ги попречуваат секојдневните активности'],
                    ['value' => 'severe', 'label' => 'Силни — многу тешко се поднесуваат'],
                ],
            ],
            [
                'step_key' => 'duration',
                'type' => TriageStepType::SingleSelect,
                'label' => 'Колку долго ги имате овие симптоми?',
                'options' => [
                    ['value' => 'under_24h', 'label' => 'Помалку од 24 часа'],
                    ['value' => '1_7_days', 'label' => '1–7 дена'],
                    ['value' => 'over_week', 'label' => 'Повеќе од една недела'],
                ],
            ],
        ];

        foreach ($steps as $index => $stepData) {
            $step = TriageStep::query()->updateOrCreate(
                [
                    'triage_flow_id' => $flow->id,
                    'step_key' => $stepData['step_key'],
                ],
                [
                    'type' => $stepData['type'],
                    'label' => $stepData['label'],
                    'sort_order' => $index,
                    'is_required' => true,
                ],
            );

            foreach ($stepData['options'] as $optionIndex => $option) {
                TriageStepOption::query()->updateOrCreate(
                    [
                        'triage_step_id' => $step->id,
                        'value' => $option['value'],
                    ],
                    [
                        'label' => $option['label'],
                        'sort_order' => $optionIndex,
                    ],
                );
            }
        }
    }

    private function seedOutcomes(TriageFlow $flow): void
    {
        $outcomes = [
            [
                'code' => TriageSessionService::EMERGENCY_OUTCOME_CODE,
                'title' => 'Веднаш побарајте итна помош',
                'body' => 'Според вашите одговори, треба веднаш да ја повикате службата за итна помош. Не ја користете оваа веб-страница наместо итна медицинска помош.',
                'handoffs' => [
                    ['type' => 'emergency', 'label' => 'Броеви за итни случаи'],
                    ['type' => 'home', 'label' => 'Назад на почетната страница', 'href' => '/'],
                ],
            ],
            [
                'code' => 'seek_care_soon',
                'title' => 'Размислете за преглед наскоро',
                'body' => 'Вашите одговори упатуваат дека можеби е разумно наскоро да разговарате со здравствен работник, особено ако симптомите се влошат.',
                'handoffs' => [
                    ['type' => 'doctors', 'label' => 'Прегледајте лекари', 'href' => '/doctors'],
                    ['type' => 'facilities', 'label' => 'Прегледајте установи', 'href' => '/facilities'],
                    ['type' => 'emergency', 'label' => 'Броеви за итни случаи'],
                ],
            ],
            [
                'code' => 'general_information',
                'title' => 'Општи информации',
                'body' => 'Според оваа листа за проверка, вашите одговори не упатуваат на непосредна итна состојба. Следете ги симптомите и побарајте стручен совет ако и понатаму сте загрижени.',
                'handoffs' => [
                    ['type' => 'doctors', 'label' => 'Прегледајте лекари', 'href' => '/doctors'],
                    ['type' => 'facilities', 'label' => 'Прегледајте установи', 'href' => '/facilities'],
                    ['type' => 'home', 'label' => 'Назад на почетната страница', 'href' => '/'],
                ],
            ],
        ];

        foreach ($outcomes as $outcome) {
            TriageOutcome::query()->updateOrCreate(
                ['triage_flow_id' => $flow->id, 'code' => $outcome['code']],
                [
                    'title' => $outcome['title'],
                    'body' => $outcome['body'],
                    'handoffs' => $outcome['handoffs'],
                ],
            );
        }
    }

    private function seedRules(TriageFlow $flow): void
    {
        TriageRule::query()->where('triage_flow_id', $flow->id)->delete();

        $rules = [
            [
                'priority' => 10,
                'outcome_code' => 'seek_care_soon',
                'conditions' => [
                    'step' => 'severity',
                    'operator' => 'in',
                    'values' => ['severe'],
                ],
            ],
            [
                'priority' => 20,
                'outcome_code' => 'seek_care_soon',
                'conditions' => [
                    'all' => [
                        [
                            'step' => 'duration',
                            'operator' => 'in',
                            'values' => ['over_week'],
                        ],
                        [
                            'step' => 'severity',
                            'operator' => 'in',
                            'values' => ['moderate', 'severe'],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($rules as $rule) {
            TriageRule::query()->create([
                'triage_flow_id' => $flow->id,
                'priority' => $rule['priority'],
                'outcome_code' => $rule['outcome_code'],
                'conditions' => $rule['conditions'],
            ]);
        }
    }
}
