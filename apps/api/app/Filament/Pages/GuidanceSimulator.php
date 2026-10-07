<?php

namespace App\Filament\Pages;

use App\Models\TriageFlowVersion;
use App\Models\User;
use App\Services\Triage\V2\AnswerNormalizer;
use App\Services\Triage\V2\ConditionEvaluator;
use App\Services\Triage\V2\Demographics;
use App\Services\Triage\V2\FlowWalker;
use App\Services\Triage\V2\GlobalScreen;
use App\Services\Triage\V2\WalkResult;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Staff-only preview of any flow version, published or not: pick who the
 * visitor is, tick red flags, answer each question with any value and see the
 * path, the scores, each node's routing and the outcome. Uses the engine's
 * own FlowWalker, so what is shown here is what visitors would get. Nothing
 * is stored — no session, no answer, no count.
 */
class GuidanceSimulator extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPlay;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Guidance simulator';

    protected static ?string $slug = 'guidance-simulator';

    protected string $view = 'filament.pages.guidance-simulator';

    #[Url]
    public ?int $version = null;

    public string $ageValue = '30';

    public string $ageUnit = 'years';

    public string $sex = 'female';

    public string $pregnancy = 'not_pregnant';

    /** @var list<string> */
    public array $conditions = [];

    /** @var list<string> */
    public array $redFlags = [];

    /** @var array<string, list<string>> */
    public array $answers = [];

    /** @var list<string> the multi question's ticked values */
    public array $multi = [];

    public string $number = '';

    public ?string $error = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('triage_flows.view');
    }

    /**
     * Without a version the page lists every flow's newest version to pick from.
     *
     * @return list<TriageFlowVersion>
     */
    public function choices(): array
    {
        return TriageFlowVersion::query()
            ->with('flow')
            ->whereIn('id', TriageFlowVersion::query()->selectRaw('MAX(id)')->groupBy('triage_flow_id'))
            ->get()
            ->sortBy(fn (TriageFlowVersion $v) => $v->title())
            ->values()
            ->all();
    }

    public function record(): ?TriageFlowVersion
    {
        return $this->version ? TriageFlowVersion::query()->with('flow')->find($this->version) : null;
    }

    public function getTitle(): string
    {
        $record = $this->record();

        return $record ? 'Simulate: '.$record->title().' (v'.$record->version.', '.$record->status.')' : 'Guidance simulator';
    }

    public function demographics(): ?Demographics
    {
        try {
            return Demographics::fromInput([
                'age_value' => $this->ageValue,
                'age_unit' => $this->ageUnit,
                'sex' => $this->sex,
                'pregnancy' => $this->pregnancy,
                'conditions' => $this->conditions,
            ]);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * The combined screen for this flow alone, as a visitor with these
     * demographics would see it.
     *
     * @return list<array{code: string, label: string, group: string, outcome: string}>
     */
    public function screen(): array
    {
        $demo = $this->demographics();
        $record = $this->record();

        if ($demo === null || $record === null) {
            return [];
        }

        $evaluator = new ConditionEvaluator($demo->toConditionValues(), []);
        $items = [];

        foreach (GlobalScreen::load()->redFlags as $flag) {
            if (! isset($flag['when']) || $evaluator->evaluate($flag['when'])) {
                $items[] = ['code' => 'global.'.$flag['code'], 'label' => $flag['label'], 'group' => 'Global', 'outcome' => $flag['outcome'] ?? 'global:emergency_now'];
            }
        }

        foreach ($record->definition['red_flags'] as $flag) {
            if (! isset($flag['when']) || $evaluator->evaluate($flag['when'])) {
                $items[] = ['code' => 'flow.'.$flag['code'], 'label' => $flag['label'], 'group' => 'This flow', 'outcome' => $flag['outcome'] ?? 'global:emergency_now'];
            }
        }

        return $items;
    }

    public function walk(): ?WalkResult
    {
        $demo = $this->demographics();
        $record = $this->record();

        if ($demo === null || $record === null) {
            return null;
        }

        return app(FlowWalker::class)->walk($record->definition, $demo->toConditionValues(), $this->answers);
    }

    /** @return array<string, mixed>|null */
    public function outcome(string $ref): ?array
    {
        if (str_starts_with($ref, 'global:')) {
            return GlobalScreen::load()->outcome(substr($ref, 7));
        }

        return $this->record()?->definition['outcomes'][$ref] ?? null;
    }

    public function audienceWarning(): ?string
    {
        $demo = $this->demographics();
        $bands = $this->record()?->definition['audience']['age_bands'] ?? [];

        if ($demo !== null && ! in_array($demo->ageBand(), $bands, true)) {
            return "Visitors in the age band {$demo->ageBand()} are not offered this flow (audience: ".implode(', ', $bands).').';
        }

        return null;
    }

    public function updated(string $property): void
    {
        // Who the visitor is changes the whole path: start over.
        if (in_array($property, ['ageValue', 'ageUnit', 'sex', 'pregnancy'], true) || str_starts_with($property, 'conditions')) {
            $this->restart();
        }
    }

    /** Answer the current question with one value (single, yes/no, scale, info). */
    public function choose(string $value): void
    {
        $this->submit([$value]);
    }

    public function submitMulti(): void
    {
        $this->submit($this->multi);
    }

    public function submitNumber(): void
    {
        $this->submit([trim($this->number)]);
    }

    /** @param  list<string>  $values */
    private function submit(array $values): void
    {
        $this->error = null;
        $walk = $this->walk();
        $current = $walk?->current;

        if ($current === null) {
            return;
        }

        try {
            $node = $this->record()->definition['nodes'][$current];
            $this->answers[$current] = app(AnswerNormalizer::class)->normalize($node, $values);
            $this->multi = [];
            $this->number = '';
        } catch (ValidationException) {
            $this->error = 'That answer is not valid for this question (out of range, or not one of its options).';
        }
    }

    /** Go back to a node on the path: its answer and everything after it are dropped. */
    public function backTo(string $nodeId): void
    {
        $path = $this->walk()->path ?? [];
        $index = array_search($nodeId, $path, true);

        if ($index === false) {
            return;
        }

        $this->answers = array_intersect_key($this->answers, array_flip(array_slice($path, 0, $index)));
        $this->error = null;
    }

    public function restart(): void
    {
        $this->answers = [];
        $this->redFlags = [];
        $this->multi = [];
        $this->number = '';
        $this->error = null;
    }

    /** @return array<string, string> */
    public function conditionOptions(): array
    {
        return array_combine(Demographics::CONDITIONS, Demographics::CONDITIONS);
    }

    public function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
