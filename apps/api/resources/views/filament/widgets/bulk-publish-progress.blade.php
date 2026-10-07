<x-filament-widgets::widget>
    @if ($operation)
        <div
            @if ($operation->isRunning())
                wire:poll.3s.keep-alive="tick"
            @endif
        >
            <x-filament::section>
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            @switch($operation->status)
                                @case(\App\Enums\BulkOperationStatus::Running)
                                    Објавување во тек — {{ $label }}
                                    @break
                                @case(\App\Enums\BulkOperationStatus::Completed)
                                    Објавувањето заврши — {{ $label }}
                                    @break
                                @case(\App\Enums\BulkOperationStatus::Failed)
                                    Објавувањето застана — {{ $label }}
                                    @break
                                @default
                                    Објавувањето е прекинато — {{ $label }}
                            @endswitch
                        </p>
                        <p class="text-sm tabular-nums text-gray-600 dark:text-gray-400">
                            {{ number_format($operation->processed, 0, ',', '.') }} / {{ number_format($operation->total, 0, ',', '.') }}
                        </p>
                    </div>

                    <div
                        role="progressbar"
                        aria-label="Напредок на објавувањето"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $percent }}"
                        style="height: .5rem; border-radius: 9999px; background: rgba(127, 127, 127, .2); overflow: hidden"
                    >
                        <div style="height: 100%; width: {{ $percent }}%; background: var(--success-500, #16a34a); transition: width .4s"></div>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Објавени {{ $operation->published }}
                        @if ($operation->skipped > 0)
                            · прескокнати {{ $operation->skipped }}
                        @endif
                        @if ($operation->failed > 0)
                            · неуспешни {{ $operation->failed }}
                        @endif
                        @if ($operation->isRunning())
                            @if ($operation->driver === \App\Models\BulkOperation::DRIVER_INLINE || $stalled)
                                · продолжува додека оваа страница е отворена
                            @else
                                · во позадина; може да ја затворите страницата
                            @endif
                        @endif
                    </p>

                    @if ($operation->error)
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $operation->error }}</p>
                    @endif

                    @if ($canManage && $operation->status === \App\Enums\BulkOperationStatus::Failed)
                        <div class="flex gap-2">
                            <x-filament::button size="sm" wire:click="resume">Продолжи</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="cancel" wire:confirm="Да се прекине објавувањето? Објавеното останува објавено.">Прекини</x-filament::button>
                        </div>
                    @elseif ($canManage && $operation->isRunning())
                        <div>
                            <x-filament::button size="sm" color="gray" wire:click="cancel" wire:confirm="Да се прекине објавувањето? Објавеното останува објавено.">Прекини</x-filament::button>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-widgets::widget>
