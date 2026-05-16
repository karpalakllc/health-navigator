<?php

namespace App\Filament\Resources\Languages\Tables;

use App\Filament\Support\TaxonomyTable;
use Filament\Tables\Table;

class LanguagesTable
{
    public static function configure(Table $table): Table
    {
        return TaxonomyTable::configure($table);
    }
}
