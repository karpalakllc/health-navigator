<?php

namespace App\Filament\Resources\UsernameTerms;

use App\Filament\Resources\UsernameTerms\Pages\CreateUsernameTerm;
use App\Filament\Resources\UsernameTerms\Pages\EditUsernameTerm;
use App\Filament\Resources\UsernameTerms\Pages\ListUsernameTerms;
use App\Filament\Resources\UsernameTerms\Schemas\UsernameTermForm;
use App\Filament\Resources\UsernameTerms\Tables\UsernameTermsTable;
use App\Models\UsernameTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The blocked and reserved username lists (UsernameTermPolicy:
 * usernames.manage). Changes apply to the next username anyone chooses;
 * existing usernames are not re-checked — rename one with „Rename username“
 * on the account.
 */
class UsernameTermResource extends Resource
{
    protected static ?string $model = UsernameTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Username rules';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 13;

    protected static ?string $modelLabel = 'username term';

    protected static ?string $pluralModelLabel = 'username terms';

    protected static ?string $recordTitleAttribute = 'term';

    public static function form(Schema $schema): Schema
    {
        return UsernameTermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsernameTermsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsernameTerms::route('/'),
            'create' => CreateUsernameTerm::route('/create'),
            'edit' => EditUsernameTerm::route('/{record}/edit'),
        ];
    }
}
