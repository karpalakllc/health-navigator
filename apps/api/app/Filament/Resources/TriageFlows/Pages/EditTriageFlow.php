<?php

namespace App\Filament\Resources\TriageFlows\Pages;

use App\Filament\Resources\TriageFlows\TriageFlowResource;
use App\Support\PublicWebUrl;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditTriageFlow extends EditRecord
{
    protected static string $resource = TriageFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewGuidance')
                ->label('View on site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): ?string => PublicWebUrl::guidance())
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) $this->getRecord()?->is_published
                    && PublicWebUrl::configured()),
        ];
    }
}
