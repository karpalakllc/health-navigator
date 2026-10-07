<?php

namespace App\Filament\Resources\GuidanceFlows\Pages;

use App\Filament\Pages\GuidanceSimulator;
use App\Filament\Resources\GuidanceFlows\GuidanceFlowResource;
use App\Models\TriageFlow;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewGuidanceFlow extends ViewRecord
{
    protected static string $resource = GuidanceFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulate')
                ->label('Simulate latest version')
                ->icon('heroicon-o-play')
                ->url(function (): ?string {
                    /** @var TriageFlow $flow */
                    $flow = $this->getRecord();
                    $latest = $flow->versions()->first();

                    return $latest ? GuidanceSimulator::getUrl(['version' => $latest->id]) : null;
                }),
        ];
    }
}
