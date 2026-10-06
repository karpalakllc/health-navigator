<?php

namespace App\Filament\Resources\ContentReports;

use App\Filament\Resources\ContentReports\Pages\ListContentReports;
use App\Filament\Resources\ContentReports\Pages\ViewContentReport;
use App\Filament\Resources\ContentReports\Schemas\ContentReportInfolist;
use App\Filament\Resources\ContentReports\Tables\ContentReportsTable;
use App\Models\ContentReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The member report queue (docs/notice-and-action.md): reports of published
 * reviews, forum topics and replies, resolved as kept or hidden.
 */
class ContentReportResource extends Resource
{
    protected static ?string $model = ContentReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'report';

    protected static ?string $pluralModelLabel = 'reports';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ContentReport::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContentReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentReports::route('/'),
            'view' => ViewContentReport::route('/{record}'),
        ];
    }
}
