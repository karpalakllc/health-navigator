<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Filament\Support\TaxonomyForm;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(TaxonomyForm::schema());
    }
}
