<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Doctor;
use App\Models\Review;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('rating'),
                TextEntry::make('body')
                    ->columnSpanFull(),
                TextEntry::make('user.name')
                    ->label('Author'),
                TextEntry::make('user.email')
                    ->label('Author email'),
                TextEntry::make('reviewable_label')
                    ->label('Target')
                    ->state(function (Review $record): string {
                        $reviewable = $record->reviewable;

                        if ($reviewable === null) {
                            return '—';
                        }

                        return $record->reviewable_type === Doctor::class
                            ? 'Doctor: '.$reviewable->full_name.' ('.$reviewable->slug.')'
                            : 'Facility: '.$reviewable->name.' ('.$reviewable->slug.')';
                    }),
                TextEntry::make('rejection_note')
                    ->visible(fn (Review $record): bool => filled($record->rejection_note))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('published_at')
                    ->dateTime(),
                TextEntry::make('moderated_at')
                    ->dateTime(),
            ]);
    }
}
