<?php

namespace App\Filament\Resources\ForumPosts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ForumPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status'),
                TextEntry::make('topic.title')->label('Topic'),
                TextEntry::make('user.name')->label('Author'),
                TextEntry::make('body')->columnSpanFull(),
                TextEntry::make('rejection_note')->columnSpanFull(),
            ]);
    }
}
