<?php

namespace App\Filament\Resources\ForumTopics;

use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\ForumTopics\Pages\ViewForumTopic;
use App\Filament\Resources\ForumTopics\Schemas\ForumTopicInfolist;
use App\Filament\Resources\ForumTopics\Tables\ForumTopicsTable;
use App\Models\ForumTopic;
use App\Support\ForumModerationScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ForumTopicResource extends Resource
{
    protected static ?string $model = ForumTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?string $navigationLabel = 'Forum topics';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return ForumModerationScope::restrictTopics(parent::getEloquentQuery());
    }

    public static function infolist(Schema $schema): Schema
    {
        return ForumTopicInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ForumTopicsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumTopics::route('/'),
            'view' => ViewForumTopic::route('/{record}'),
        ];
    }
}
