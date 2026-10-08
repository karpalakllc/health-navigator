<?php

namespace App\Filament\Resources\ImportRuns;

use App\Enums\ImportRunStatus;
use App\Filament\Resources\ImportRuns\Pages\ListImportRuns;
use App\Filament\Resources\ImportRuns\Pages\ViewImportRun;
use App\Models\ImportRun;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every import run (ФЗОМ, Лекарска комора, institution websites): when, dry
 * run or apply, outcome, counts, and the diff summary as a CSV download.
 * Runs start from the command line (docs/data-import.md).
 */
class ImportRunResource extends Resource
{
    protected static ?string $model = ImportRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Import runs';

    protected static string|\UnitEnum|null $navigationGroup = 'Data import';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('source')->badge(),
            TextEntry::make('dry_run')->label('Mode')->formatStateUsing(fn (bool $state): string => $state ? 'Dry run (nothing written)' : 'Apply'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (ImportRunStatus $state): string => str_replace('_', ' ', ucfirst($state->value))),
            TextEntry::make('started_at')->dateTime(),
            TextEntry::make('finished_at')->dateTime()->placeholder('—'),
            TextEntry::make('triggeredBy.name')->label('Started by')->placeholder('Command line / scheduler'),
            TextEntry::make('error')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('counts')
                ->state(fn (ImportRun $record): array => array_map('strval', $record->counts ?? []))
                ->columnSpanFull(),
            KeyValueEntry::make('files')
                ->label('Source files')
                ->state(fn (ImportRun $record): array => collect((array) ($record->source_meta['files'] ?? []))
                    ->map(fn ($file): string => is_array($file)
                        ? trim(sprintf('%s · %s bytes · %s', $file['status'] ?? '', $file['bytes'] ?? '?', $file['last_modified'] ?? ''), ' ·')
                        : (string) $file)
                    ->all())
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('source')->badge(),
                IconColumn::make('dry_run')->label('Dry run')->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ImportRunStatus $state): string => str_replace('_', ' ', ucfirst($state->value)))
                    ->color(fn (ImportRunStatus $state): string => match ($state) {
                        ImportRunStatus::Succeeded => 'success',
                        ImportRunStatus::NotModified => 'gray',
                        ImportRunStatus::Running => 'warning',
                        ImportRunStatus::Failed => 'danger',
                    }),
                TextColumn::make('created')
                    ->label('New doctors / facilities')
                    ->state(fn (ImportRun $record): string => sprintf('%d / %d', $record->count('doctors_created') + $record->count('dentists_created'), $record->count('facilities_created'))),
                TextColumn::make('review')
                    ->label('Raised for review')
                    ->state(fn (ImportRun $record): int => collect($record->counts ?? [])->filter(fn ($value, $key) => str_starts_with((string) $key, 'review_'))->sum()),
                TextColumn::make('started_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('source')->options(fn (): array => ImportRun::query()->distinct()->orderBy('source')->pluck('source', 'source')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::downloadDiffAction(),
            ]);
    }

    public static function downloadDiffAction(): Action
    {
        return Action::make('downloadDiff')
            ->label('Diff (CSV)')
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn (ImportRun $record): bool => $record->diff_path !== null && (auth()->user()?->can('view', $record) ?? false))
            ->action(function (ImportRun $record): ?StreamedResponse {
                $disk = Storage::disk((string) config('import.disk'));

                if ($record->diff_path === null || ! $disk->exists($record->diff_path)) {
                    return null;
                }

                return $disk->download($record->diff_path, sprintf('import-%s-%d%s.csv', $record->source, $record->getKey(), $record->dry_run ? '-dry-run' : ''));
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportRuns::route('/'),
            'view' => ViewImportRun::route('/{record}'),
        ];
    }
}
