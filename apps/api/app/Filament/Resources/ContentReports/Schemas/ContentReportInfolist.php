<?php

namespace App\Filament\Resources\ContentReports\Schemas;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Support\ReportedContentLabel;
use App\Models\ContentReport;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ContentReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => ucfirst($state->value)),
                TextEntry::make('reason')
                    ->formatStateUsing(fn (ReportReason $state): string => $state->label()),
                TextEntry::make('target')
                    ->label('Reported content')
                    ->state(fn (ContentReport $record): string => ReportedContentLabel::target($record)),
                TextEntry::make('content_status')
                    ->label('Content is')
                    ->state(fn (ContentReport $record): string => ReportedContentLabel::status($record)),
                TextEntry::make('content_body')
                    ->label('Content')
                    ->state(fn (ContentReport $record): string => ReportedContentLabel::body($record))
                    ->columnSpanFull(),
                TextEntry::make('note')
                    ->label('Reporter note')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('user.name')
                    ->label('Reporter'),
                TextEntry::make('open_reports')
                    ->label('Open reports on this item')
                    ->state(fn (ContentReport $record): int => $record->openReportsOnSameContent()),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('resolvedBy.name')
                    ->label('Resolved by')
                    ->placeholder('—'),
                TextEntry::make('resolved_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
