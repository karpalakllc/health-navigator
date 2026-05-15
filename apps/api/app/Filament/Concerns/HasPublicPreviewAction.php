<?php

namespace App\Filament\Concerns;

use App\Support\PublicWebUrl;
use Filament\Actions\Action;

trait HasPublicPreviewAction
{
    protected function getPublicPreviewAction(): Action
    {
        return Action::make('viewPublic')
            ->label('View on site')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (): ?string => PublicWebUrl::forRecord($this->getRecord()))
            ->openUrlInNewTab()
            ->visible(fn (): bool => (bool) $this->getRecord()?->is_published
                && PublicWebUrl::configured()
                && PublicWebUrl::forRecord($this->getRecord()) !== null);
    }
}
