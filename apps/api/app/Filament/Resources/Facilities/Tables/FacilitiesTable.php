<?php

namespace App\Filament\Resources\Facilities\Tables;

use App\Enums\FacilityType;
use App\Filament\Support\DirectoryTableColumns;
use App\Filament\Support\UrgentCareForm;
use App\Filament\Support\VerificationActions;
use App\Filament\Tables\Filters\PublicationStatusFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                DirectoryTableColumns::hiddenSlug(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (FacilityType $state): string => ucfirst($state->value))
                    ->color(fn (FacilityType $state): string => match ($state) {
                        FacilityType::Hospital => 'danger',
                        FacilityType::Clinic => 'info',
                        FacilityType::Laboratory => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('has_emergency_services')
                    ->label('Emergency dept.')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('has_emergency_medical_service')
                    ->label('Emergency medical service')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                DirectoryTableColumns::publicationBadge(),
                VerificationActions::statusColumn(),
                DirectoryTableColumns::updatedAt(),
            ])
            ->defaultSort('name')
            ->filters([
                PublicationStatusFilter::make(),
                VerificationActions::filter(),
                SelectFilter::make('urgent_care')
                    ->label('Urgent care')
                    ->options([
                        'ed' => 'Emergency department',
                        'ems' => 'Emergency medical service',
                        'clinic' => 'On-duty clinic',
                        'dental' => 'Dental emergency',
                        'any' => 'Any urgent service',
                        'to_check' => 'Import evidence, not confirmed by staff',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'ed', 'ems', 'clinic', 'dental' => UrgentCareForm::scopeService($query, $data['value']),
                        'any' => UrgentCareForm::scopeService($query, null),
                        'to_check' => UrgentCareForm::scopeToCheck($query),
                        default => $query,
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
