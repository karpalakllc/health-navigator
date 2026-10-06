<?php

namespace App\Filament\Resources\ProfileCorrections\Pages;

use App\Filament\Resources\ProfileCorrections\ProfileCorrectionResource;
use App\Models\ProfileCorrection;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewProfileCorrection extends ViewRecord
{
    protected static string $resource = ProfileCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editProfile')
                ->label('Edit the profile')
                ->icon('heroicon-o-pencil')
                ->color('gray')
                ->url(fn (ProfileCorrection $record): ?string => ProfileCorrectionResource::profileEditUrl($record))
                ->visible(fn (ProfileCorrection $record): bool => ProfileCorrectionResource::profileEditUrl($record) !== null),
            ProfileCorrectionResource::resolveAction(),
            ProfileCorrectionResource::declineAction(),
        ];
    }
}
