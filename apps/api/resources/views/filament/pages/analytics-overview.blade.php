<x-filament-panels::page>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <label for="analytics-period" class="text-sm font-medium text-gray-950 dark:text-white">
                Reporting period
            </label>
            <select
                id="analytics-period"
                wire:model.live="days"
                class="mt-1 block rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            >
                @foreach ($this->periodOptions() as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div wire:key="analytics-header-{{ $days }}">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="['md' => 2, 'xl' => 2]"
        />
    </div>

    @if (filled(config('services.plausible.domain')))
        <x-filament::section heading="Frontend traffic (Plausible)" class="mt-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Page views and referrers are collected in Plausible for
                <strong>{{ config('services.plausible.domain') }}</strong>.
                Set <code class="text-xs">PLAUSIBLE_DOMAIN</code> in the API env and
                <code class="text-xs">NEXT_PUBLIC_PLAUSIBLE_DOMAIN</code> on the web app to match.
            </p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Open your Plausible dashboard for detailed traffic analytics (top pages, devices, referrers).
            </p>
        </x-filament::section>
    @else
        <x-filament::section heading="Frontend traffic" class="mt-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Plausible is not configured. Add <code class="text-xs">NEXT_PUBLIC_PLAUSIBLE_DOMAIN</code> on the
                web app when you are ready to track public page views.
            </p>
        </x-filament::section>
    @endif

    <x-filament::section heading="Users & community" class="mt-6">
        <div wire:key="analytics-charts-{{ $days }}">
            <x-filament-widgets::widgets
                :widgets="[
                    \App\Filament\Widgets\AnalyticsActivityChart::make(['days' => $days]),
                    \App\Filament\Widgets\AnalyticsCommunityChart::make(['days' => $days]),
                ]"
                :columns="['md' => 1, 'xl' => 2]"
            />
        </div>
    </x-filament::section>

    <div wire:key="analytics-search-{{ $days }}" class="mt-6">
        <x-filament-widgets::widgets
            :widgets="[
                \App\Filament\Widgets\AnalyticsSearchInsights::make(['days' => $days]),
            ]"
            :columns="1"
        />
    </div>
</x-filament-panels::page>
