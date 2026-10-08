<?php

namespace App\Filament\Resources\ImportSuppressions;

use App\Filament\Resources\ImportSuppressions\Pages\ListImportSuppressions;
use App\Models\ImportSuppression;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Suppressed profiles: doctors no import may create or publish again —
 * removed after an upheld objection, or deleted by staff (docs/data-import.md
 * §9). Staff with imports.manage lift one deliberately, e.g. when the person
 * withdraws the objection or a duplicate was deleted by mistake; the next
 * import may then create the profile again (as a hidden draft).
 */
class ImportSuppressionResource extends Resource
{
    protected static ?string $model = ImportSuppression::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Suppressed profiles';

    protected static string|\UnitEnum|null $navigationGroup = 'Data import';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'suppressed profile';

    protected static ?string $pluralModelLabel = 'suppressed profiles';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Profile')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ImportSuppression::REASON_OBJECTION ? 'Objection upheld' : 'Deleted by staff')
                    ->color(fn (string $state): string => $state === ImportSuppression::REASON_OBJECTION ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Since')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('lifted_at')
                    ->label('Lifted')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('lifted_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('lifted_at'),
                    ),
            ])
            ->recordActions([
                Action::make('lift')
                    ->label('Lift')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (ImportSuppression $record): string => $record->reason === ImportSuppression::REASON_OBJECTION
                        ? 'This person objected and the objection was upheld. Lift only if they withdrew it in writing. The next import may create the profile again as a hidden draft.'
                        : 'The next import may create the profile again as a hidden draft.')
                    ->visible(fn (ImportSuppression $record): bool => $record->isActive() && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (ImportSuppression $record): void {
                        $user = auth()->user();

                        if (! $record->isActive() || ! $user instanceof User || ! $user->can('update', $record)) {
                            return;
                        }

                        $record->lift($user);
                        Notification::make()->title('Suppression lifted')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportSuppressions::route('/'),
        ];
    }
}
