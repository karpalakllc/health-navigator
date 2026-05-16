<?php

namespace App\Filament\Resources\Procedures\Schemas;

use App\Filament\Support\TaxonomyForm;
use Filament\Schemas\Schema;

class ProcedureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(TaxonomyForm::schema());
    }
}
