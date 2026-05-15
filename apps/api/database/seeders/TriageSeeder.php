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
    public function run(): void
    {
        $flow = TriageFlow::query()->updateOrCreate(
            ['title' => 'General symptom guidance'],
            [
                'intro_body' => 'Answer a few general questions to see informational next steps. This is not medical advice and cannot diagnose conditions.',
                'is_published' => true,
            ],
        );

        $this->seedRedFlags($flow);
        $this->seedSteps($flow);
        $this->seedOutcomes($flow);
        $this->seedRules($flow);
    }

    private function seedRedFlags(TriageFlow $flow): void
    {
        $flags = [
            ['code' => 'chest_pain', 'label' => 'Severe chest pain or pressure'],
            ['code' => 'breathing', 'label' => 'Severe difficulty breathing'],
            ['code' => 'bleeding', 'label' => 'Heavy bleeding that does not stop'],
            ['code' => 'confusion', 'label' => 'Sudden confusion or inability to wake'],
            ['code' => 'self_harm', 'label' => 'Thoughts of self-harm or suicide'],
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
                'label' => 'Age group',
                'options' => [
                    ['value' => 'under_18', 'label' => 'Under 18'],
                    ['value' => '18_64', 'label' => '18–64'],
                    ['value' => 'over_64', 'label' => '65 or older'],
                ],
            ],
            [
                'step_key' => 'concern',
                'type' => TriageStepType::SingleSelect,
                'label' => 'What best describes your concern?',
                'options' => [
                    ['value' => 'general', 'label' => 'General symptoms (pain, fever, fatigue)'],
                    ['value' => 'injury', 'label' => 'Injury or accident'],
                    ['value' => 'wellbeing', 'label' => 'Stress or wellbeing'],
                ],
            ],
            [
                'step_key' => 'severity',
                'type' => TriageStepType::SingleSelect,
                'label' => 'How would you describe the severity today?',
                'options' => [
                    ['value' => 'mild', 'label' => 'Mild — noticeable but manageable'],
                    ['value' => 'moderate', 'label' => 'Moderate — interfering with daily activities'],
                    ['value' => 'severe', 'label' => 'Severe — very difficult to manage'],
                ],
            ],
            [
                'step_key' => 'duration',
                'type' => TriageStepType::SingleSelect,
                'label' => 'How long have you had these symptoms?',
                'options' => [
                    ['value' => 'under_24h', 'label' => 'Less than 24 hours'],
                    ['value' => '1_7_days', 'label' => '1–7 days'],
                    ['value' => 'over_week', 'label' => 'More than a week'],
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
                'title' => 'Seek emergency care now',
                'body' => 'Based on your answers, you should contact emergency services immediately. Do not use this website instead of urgent care.',
                'handoffs' => [
                    ['type' => 'emergency', 'label' => 'Emergency numbers'],
                    ['type' => 'home', 'label' => 'Return home', 'href' => '/'],
                ],
            ],
            [
                'code' => 'seek_care_soon',
                'title' => 'Consider care soon',
                'body' => 'Your answers suggest it may be reasonable to speak with a healthcare professional soon, especially if symptoms worsen.',
                'handoffs' => [
                    ['type' => 'doctors', 'label' => 'Browse doctors', 'href' => '/doctors'],
                    ['type' => 'facilities', 'label' => 'Browse facilities', 'href' => '/facilities'],
                    ['type' => 'emergency', 'label' => 'Emergency numbers'],
                ],
            ],
            [
                'code' => 'general_information',
                'title' => 'General information',
                'body' => 'Your answers do not suggest an immediate emergency on this checklist. Continue to monitor symptoms and seek professional advice if you remain concerned.',
                'handoffs' => [
                    ['type' => 'doctors', 'label' => 'Browse doctors', 'href' => '/doctors'],
                    ['type' => 'facilities', 'label' => 'Browse facilities', 'href' => '/facilities'],
                    ['type' => 'home', 'label' => 'Return home', 'href' => '/'],
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
