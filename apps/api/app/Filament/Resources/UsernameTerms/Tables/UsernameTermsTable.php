<?php

namespace App\Filament\Resources\UsernameTerms\Tables;

use App\Enums\UsernameMatchType;
use App\Enums\UsernameTermKind;
use App\Models\UsernameTerm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsernameTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term')
                    ->searchable(['term', 'term_normalized'])
                    ->sortable()
                    ->description(fn (UsernameTerm $record): string => $record->term_normalized),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (UsernameTermKind $state): string => $state->label())
                    ->color(fn (UsernameTermKind $state): string => $state === UsernameTermKind::Blocked ? 'danger' : 'warning'),
                TextColumn::make('match_type')
                    ->label('Match')
                    ->badge()
                    ->formatStateUsing(fn (UsernameMatchType $state): string => $state->label())
                    ->color(fn (UsernameMatchType $state): string => $state === UsernameMatchType::Allowed ? 'success' : 'gray'),
                TextColumn::make('language')->sortable(),
                TextColumn::make('category')
                    ->formatStateUsing(fn (?string $state): string => UsernameTerm::CATEGORIES[$state] ?? (string) $state)
                    ->sortable(),
                ToggleColumn::make('active'),
                TextColumn::make('note')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('term')
            ->filters([
                SelectFilter::make('kind')
                    ->options(collect(UsernameTermKind::cases())->mapWithKeys(fn (UsernameTermKind $kind): array => [$kind->value => $kind->label()])->all()),
                SelectFilter::make('match_type')
                    ->label('Match')
                    ->options(collect(UsernameMatchType::cases())->mapWithKeys(fn (UsernameMatchType $type): array => [$type->value => $type->label()])->all()),
                SelectFilter::make('language')
                    ->options(['en' => 'English', 'mk' => 'Macedonian', 'sq' => 'Albanian', 'any' => 'Any / names']),
                SelectFilter::make('category')->options(UsernameTerm::CATEGORIES),
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
