<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Filament\Support\TaxonomyTable;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return TaxonomyTable::configure($table);
    }
}
