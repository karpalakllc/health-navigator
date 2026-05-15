<?php

namespace App\Filament\Resources\ForumTopics\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ForumTopicInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status'),
                TextEntry::make('title'),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('user.name')->label('Author'),
                TextEntry::make('body')->columnSpanFull(),
                TextEntry::make('rejection_note')->columnSpanFull(),
            ]);
    }
}
