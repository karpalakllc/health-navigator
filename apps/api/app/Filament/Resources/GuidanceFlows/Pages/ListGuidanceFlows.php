<?php

namespace App\Filament\Resources\GuidanceFlows\Pages;

use App\Filament\Pages\GuidanceOutcomes;
use App\Filament\Resources\GuidanceFlows\GuidanceFlowResource;
use App\Models\TriageFlow;
use App\Services\Triage\V2\FlowImporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListGuidanceFlows extends ListRecords
{
    protected static string $resource = GuidanceFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import from files')
                ->icon('heroicon-o-arrow-down-tray')
                ->authorize(fn (): bool => auth()->user()?->can('create', TriageFlow::class) ?? false)
                ->requiresConfirmation()
                ->modalDescription('Loads database/data/triage/flows/*.json. Changed files become new draft versions that stay hidden until a clinician review is recorded and the version is published. Files that fail the linter are skipped.')
                ->action(function (FlowImporter $importer): void {
                    $result = $importer->import();
                    $counts = array_count_values(array_column($result['files'], 'result'));
                    $rejected = array_values(array_map(
                        fn (array $file) => basename($file['path']).': '.($file['report']->errors[0] ?? ''),
                        array_filter($result['files'], fn (array $file) => $file['result'] === 'rejected'),
                    ));

                    if (! $result['global']->ok()) {
                        Notification::make()->title('Global screen fails the linter — nothing imported')
                            ->body(implode("\n", array_slice($result['global']->errors, 0, 5)))
                            ->danger()->persistent()->send();

                        return;
                    }

                    $notification = Notification::make()
                        ->title(sprintf(
                            'Imported %d, unchanged %d, rejected %d',
                            ($counts['imported'] ?? 0) + ($counts['published'] ?? 0),
                            $counts['unchanged'] ?? 0,
                            $counts['rejected'] ?? 0,
                        ))
                        ->body($rejected === [] ? null : implode("\n", array_slice($rejected, 0, 8)))
                        ->status($rejected === [] ? 'success' : 'warning');

                    if ($rejected !== []) {
                        $notification->persistent();
                    }

                    $notification->send();
                }),
            Action::make('outcomes')
                ->label('Outcome counts')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(fn (): string => GuidanceOutcomes::getUrl()),
        ];
    }
}
