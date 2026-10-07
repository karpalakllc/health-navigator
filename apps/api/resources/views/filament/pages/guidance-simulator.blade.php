@php
    $record = $this->record();
    $definition = $record?->definition ?? [];
    $demo = $this->demographics();
    $screen = $this->screen();
    $flagged = array_values(array_filter($screen, fn ($item) => in_array($item['code'], $redFlags, true)));
    $walk = $flagged === [] ? $this->walk() : null;
    $nodes = $definition['nodes'] ?? [];
    $current = $walk?->current;
    $node = $current ? $nodes[$current] : null;
    $label = 'font-size:.875rem;font-weight:500;';
    $muted = 'font-size:.8rem;opacity:.7;';
    $chip = 'display:inline-block;padding:.125rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;background:rgba(127,127,127,.15);';
    $answerLabel = function (string $id, array $values) use ($nodes): string {
        $n = $nodes[$id] ?? [];
        if (($n['type'] ?? '') === 'info') {
            return 'seen';
        }
        $labels = [];
        foreach ($values as $value) {
            $option = collect($n['options'] ?? [])->firstWhere('value', $value);
            $labels[] = $option['label'] ?? ($value.(isset($n['unit']) && $value !== 'unknown' ? ' '.$n['unit'] : ''));
        }
        return $labels === [] ? '(skipped)' : implode(', ', $labels);
    };
@endphp

<x-filament-panels::page>
    @if (! $record)
        <x-filament::section heading="Choose a flow">
            <ul style="display:grid;gap:.5rem;">
                @forelse ($this->choices() as $choice)
                    <li><x-filament::link :href="\App\Filament\Pages\GuidanceSimulator::getUrl(['version' => $choice->id])">{{ $choice->title() }} — v{{ $choice->version }} ({{ $choice->status }})</x-filament::link></li>
                @empty
                    <li>No flows imported yet (Guidance flows → Import from files).</li>
                @endforelse
            </ul>
        </x-filament::section>
    @else
    <x-filament::section heading="Visitor">
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="{{ $label }}">Age</span>
                <div style="display:flex;gap:.5rem;">
                    <x-filament::input.wrapper style="width:6rem;">
                        <x-filament::input type="number" min="0" wire:model.live.debounce.400ms="ageValue" />
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="ageUnit">
                            <option value="years">years</option>
                            <option value="months">months</option>
                            <option value="weeks">weeks</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="{{ $label }}">Sex at birth</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sex">
                        <option value="female">female</option>
                        <option value="male">male</option>
                        <option value="unspecified">unspecified</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if (($demo && $demo->pregnancy !== 'not_asked') || ($demo === null && $sex !== 'male'))
                <label style="display:flex;flex-direction:column;gap:.25rem;">
                    <span style="{{ $label }}">Pregnancy</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="pregnancy">
                            <option value="not_pregnant">not pregnant</option>
                            <option value="pregnant">pregnant</option>
                            <option value="postpartum">postpartum (≤ 6 weeks)</option>
                            <option value="unsure">unsure</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            @endif
            <fieldset style="display:flex;flex-wrap:wrap;gap:.75rem;">
                <legend style="{{ $label }}">Conditions</legend>
                @foreach ($this->conditionOptions() as $value => $text)
                    <label style="display:flex;gap:.35rem;align-items:center;font-size:.875rem;">
                        <x-filament::input.checkbox wire:model.live="conditions" value="{{ $value }}" /> {{ $text }}
                    </label>
                @endforeach
            </fieldset>
            <x-filament::button color="gray" wire:click="restart" icon="heroicon-o-arrow-path">Restart</x-filament::button>
        </div>
        <p style="{{ $muted }};margin-top:.75rem;">
            @if ($demo)
                Age band <strong>{{ $demo->ageBand() }}</strong>, pregnancy <strong>{{ $demo->pregnancy }}</strong>.
            @else
                Enter a valid age.
            @endif
            Nothing here is stored or counted.
        </p>
        @if ($warning = $this->audienceWarning())
            <p style="margin-top:.5rem;font-weight:600;">{{ $warning }}</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="1. Red-flag screen (asked first)" class="mt-6">
        <div style="display:grid;gap:.5rem;">
            @foreach ($screen as $item)
                <label style="display:flex;gap:.5rem;align-items:flex-start;">
                    <x-filament::input.checkbox wire:model.live="redFlags" value="{{ $item['code'] }}" />
                    <span><span style="{{ $chip }}">{{ $item['group'] }}</span> {{ $item['label'] }} <span style="{{ $muted }}">→ {{ $item['outcome'] }}</span></span>
                </label>
            @endforeach
        </div>
    </x-filament::section>

    @if ($flagged !== [])
        @php
            $outcomes = collect($flagged)->map(fn ($f) => $this->outcome($f['outcome']))->filter()->sortByDesc(fn ($o) => (int) ($o['crisis'] ?? false));
        @endphp
        @foreach ($outcomes as $outcome)
            @include('filament.pages.partials.guidance-outcome', ['outcome' => $outcome, 'ref' => 'red flag'])
        @endforeach
    @elseif ($walk)
        <x-filament::section heading="2. Questions" class="mt-6">
            <ol style="display:grid;gap:.5rem;list-style:decimal;padding-left:1.25rem;">
                @foreach ($walk->path as $id)
                    <li>
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:baseline;justify-content:space-between;">
                            <span><span style="{{ $chip }}">{{ $id }}</span> {{ $nodes[$id]['text'] ?? $nodes[$id]['title'] ?? '' }}
                                — <strong>{{ $answerLabel($id, $answers[$id] ?? []) }}</strong></span>
                            <x-filament::link tag="button" wire:click="backTo('{{ $id }}')" size="sm">Back to here</x-filament::link>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($node)
                <div style="margin-top:1rem;padding:1rem;border:1px solid rgba(127,127,127,.3);border-radius:.75rem;display:grid;gap:.75rem;">
                    <p style="{{ $muted }}">Current: <span style="{{ $chip }}">{{ $current }}</span> {{ $node['type'] }}{{ isset($node['kind']) ? ' / '.$node['kind'] : '' }}</p>
                    @if ($node['type'] === 'info')
                        <p style="font-weight:600;">{{ $node['title'] }}</p>
                        <p>{{ $node['text'] }}</p>
                        <div><x-filament::button wire:click="choose('seen')">Continue</x-filament::button></div>
                    @else
                        <p style="font-weight:600;">{{ $node['text'] }}</p>
                        @isset($node['help'])<p style="{{ $muted }}">{{ $node['help'] }}</p>@endisset
                        @switch($node['kind'])
                            @case('single')
                                <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                                    @foreach ($node['options'] as $option)
                                        <x-filament::button color="gray" wire:click="choose('{{ $option['value'] }}')">{{ $option['label'] }}</x-filament::button>
                                    @endforeach
                                </div>
                                @break
                            @case('multi')
                                <div style="display:grid;gap:.35rem;">
                                    @foreach ($node['options'] as $option)
                                        <label style="display:flex;gap:.5rem;align-items:center;">
                                            <x-filament::input.checkbox wire:model="multi" value="{{ $option['value'] }}" /> {{ $option['label'] }}
                                            @if ($option['exclusive'] ?? false)<span style="{{ $muted }}">(exclusive)</span>@endif
                                        </label>
                                    @endforeach
                                </div>
                                <div><x-filament::button wire:click="submitMulti">Answer</x-filament::button></div>
                                @break
                            @case('yes_no')
                                <div style="display:flex;gap:.5rem;">
                                    <x-filament::button color="gray" wire:click="choose('yes')">Да</x-filament::button>
                                    <x-filament::button color="gray" wire:click="choose('no')">Не</x-filament::button>
                                    @if ($node['allow_unsure'] ?? false)
                                        <x-filament::button color="gray" wire:click="choose('unsure')">Не знам</x-filament::button>
                                    @endif
                                </div>
                                @break
                            @case('scale')
                                <div style="display:flex;flex-wrap:wrap;gap:.35rem;">
                                    @foreach (range(0, 10) as $n)
                                        <x-filament::button color="gray" size="sm" wire:click="choose('{{ $n }}')">{{ $n }}</x-filament::button>
                                    @endforeach
                                </div>
                                @break
                            @case('number')
                                <div style="display:flex;gap:.5rem;align-items:center;">
                                    <x-filament::input.wrapper style="width:8rem;">
                                        <x-filament::input type="text" inputmode="decimal" wire:model="number" />
                                    </x-filament::input.wrapper>
                                    <span>{{ $node['unit'] }} ({{ $node['min'] }}–{{ $node['max'] }})</span>
                                    <x-filament::button wire:click="submitNumber">Answer</x-filament::button>
                                    @if ($node['allow_unknown'] ?? false)
                                        <x-filament::button color="gray" wire:click="choose('unknown')">Не знам</x-filament::button>
                                    @endif
                                </div>
                                @break
                        @endswitch
                        @if ($node['optional'] ?? false)
                            <div><x-filament::link tag="button" wire:click="submitMulti">Skip (optional)</x-filament::link></div>
                        @endif
                    @endif
                    @if ($error)
                        <p style="color:rgb(220,38,38);font-weight:600;">{{ $error }}</p>
                    @endif
                    <details>
                        <summary style="{{ $muted }};cursor:pointer;">Routing of this node</summary>
                        <pre style="font-size:.75rem;white-space:pre-wrap;">{{ $this->json($node['next']) }}</pre>
                    </details>
                </div>
            @endif

            @if ($walk->scores !== [])
                <p style="{{ $muted }};margin-top:.75rem;">Scores: @foreach ($walk->scores as $id => $value)<span style="{{ $chip }}">{{ $id }} = {{ $value }}</span> @endforeach</p>
            @endif
        </x-filament::section>

        @if ($walk->outcome)
            @include('filament.pages.partials.guidance-outcome', ['outcome' => $this->outcome($walk->outcome), 'ref' => $walk->outcome])
        @elseif ($walk->broken)
            <x-filament::section class="mt-6"><p style="font-weight:600;">The walk broke (no route). Visitors would get the emergency outcome. Run the linter.</p></x-filament::section>
        @endif
    @endif

    <x-filament::section heading="All outcomes of this version" collapsible collapsed class="mt-6">
        <div style="display:grid;gap:.75rem;">
            @foreach ($definition['outcomes'] ?? [] as $id => $outcome)
                <div><span style="{{ $chip }}">{{ $id }}</span> <span style="{{ $chip }}">{{ $outcome['level'] }}</span> <strong>{{ $outcome['title'] }}</strong> — {{ $outcome['summary'] }}</div>
            @endforeach
        </div>
    </x-filament::section>
    @endif
</x-filament-panels::page>
