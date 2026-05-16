<x-filament-panels::page>
    <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="['md' => 2, 'xl' => 3]"
    />

    @if (filled(config('services.plausible.domain')))
        <x-filament::section heading="Frontend traffic (Plausible)" class="mt-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Page views and referrers are collected in Plausible for
                <strong>{{ config('services.plausible.domain') }}</strong>.
                Open your Plausible dashboard for detailed traffic analytics.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
