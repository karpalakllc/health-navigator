<x-filament-widgets::widget>
    <x-filament::section heading="Top search queries ({{ $days }} days)">
        @if (count($queries) === 0)
            <p class="text-sm text-gray-600 dark:text-gray-400">
                No unified searches recorded in this period. Searches require at least two characters.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4 font-medium text-gray-950 dark:text-white">Query</th>
                            <th class="pb-2 font-medium text-gray-950 dark:text-white">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($queries as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $row['query'] }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
