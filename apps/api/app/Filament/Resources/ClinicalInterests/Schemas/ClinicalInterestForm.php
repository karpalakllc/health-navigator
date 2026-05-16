<?php

namespace App\Filament\Resources\ClinicalInterests\Schemas;

use App\Filament\Support\TaxonomyForm;
use Filament\Schemas\Schema;

class ClinicalInterestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(TaxonomyForm::schema());
    }
}
