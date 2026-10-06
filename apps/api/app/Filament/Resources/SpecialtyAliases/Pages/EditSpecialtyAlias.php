<?php

namespace App\Filament\Resources\SpecialtyAliases\Pages;

use App\Filament\Resources\SpecialtyAliases\SpecialtyAliasResource;
use Filament\Resources\Pages\EditRecord;

class EditSpecialtyAlias extends EditRecord
{
    protected static string $resource = SpecialtyAliasResource::class;

    /**
     * An excluded wording maps to no specialty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ((bool) ($data['is_excluded'] ?? false)) {
            $data['specialty_id'] = null;
        }

        return $data;
    }
}
