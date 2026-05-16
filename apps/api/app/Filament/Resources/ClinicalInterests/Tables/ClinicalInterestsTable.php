<?php

namespace App\Filament\Resources\ClinicalInterests\Tables;

use App\Filament\Support\TaxonomyTable;
use Filament\Tables\Table;

class ClinicalInterestsTable
{
    public static function configure(Table $table): Table
    {
        return TaxonomyTable::configure($table);
    }
}
