<?php

namespace App\Filament\Resources\Languages\Schemas;

use App\Filament\Support\TaxonomyForm;
use Filament\Schemas\Schema;

class LanguageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(TaxonomyForm::schema());
    }
}
