<?php

namespace App\Filament\Resources\Procedures\Tables;

use App\Filament\Support\TaxonomyTable;
use Filament\Tables\Table;

class ProceduresTable
{
    public static function configure(Table $table): Table
    {
        return TaxonomyTable::configure($table);
    }
}
