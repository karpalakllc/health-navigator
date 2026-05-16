<?php

namespace App\Filament\Resources\ForumPosts;

use App\Filament\Resources\ForumPosts\Pages\ListForumPosts;
use App\Filament\Resources\ForumPosts\Pages\ViewForumPost;
use App\Filament\Resources\ForumPosts\Schemas\ForumPostInfolist;
use App\Filament\Resources\ForumPosts\Tables\ForumPostsTable;
use App\Models\ForumPost;
use App\Support\ForumModerationScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ForumPostResource extends Resource
{
    protected static ?string $model = ForumPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Forum replies';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return ForumModerationScope::restrictPosts(parent::getEloquentQuery());
    }

    public static function infolist(Schema $schema): Schema
    {
        return ForumPostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ForumPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumPosts::route('/'),
            'view' => ViewForumPost::route('/{record}'),
        ];
    }
}
