<?php

namespace App\Filament\Resources\LicenceSpecialtyMappings\Pages;

use App\Filament\Resources\LicenceSpecialtyMappings\LicenceSpecialtyMappingResource;
use App\Models\LicenceSpecialtyMapping;
use Filament\Resources\Pages\EditRecord;

class EditLicenceSpecialtyMapping extends EditRecord
{
    protected static string $resource = LicenceSpecialtyMappingResource::class;

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof LicenceSpecialtyMapping) {
            self::markReviewed($record);
        }
    }

    /**
     * A row staff saved is reviewed by them; the next import uses it as is.
     */
    public static function markReviewed(LicenceSpecialtyMapping $record): void
    {
        $record->forceFill([
            'reviewed_at' => now(),
            'reviewed_by_id' => auth()->id(),
        ])->saveQuietly();
    }
}
