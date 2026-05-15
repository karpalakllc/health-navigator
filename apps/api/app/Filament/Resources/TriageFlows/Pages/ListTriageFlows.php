<?php

namespace App\Filament\Resources\TriageFlows\Pages;

use App\Filament\Resources\TriageFlows\TriageFlowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTriageFlows extends ListRecords
{
    protected static string $resource = TriageFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
