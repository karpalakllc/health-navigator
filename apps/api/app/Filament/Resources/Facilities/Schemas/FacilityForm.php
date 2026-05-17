<?php

namespace App\Filament\Resources\Facilities\Schemas;

use App\Enums\FacilityType;
use App\Filament\Support\AdminSelect;
use App\Filament\Support\OptimizedImageUpload;
use App\Filament\Support\FacilityCommonForm;
use App\Models\Doctor;
use App\Support\Slug;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Fieldset::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (filled($state)) {
                                    $set('slug', Slug::fromName($state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->prefixIcon(Heroicon::OutlinedLink)
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('type')
                            ->options([
                                FacilityType::Clinic->value => 'Clinic',
                                FacilityType::Hospital->value => 'Hospital',
                                FacilityType::Laboratory->value => 'Laboratory',
                            ])
                            ->required()
                            ->native(false)
                            ->prefixIcon(Heroicon::OutlinedTag),
                        OptimizedImageUpload::avatar('avatar_url', 'facilities')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Fieldset::make('Services & departments')
                    ->schema([
                        Toggle::make('has_emergency_services')
                            ->label('Emergency services available')
                            ->helperText('Shown on the public profile when enabled.'),
                        AdminSelect::facilityDepartments(),
                    ]),

                ...FacilityCommonForm::locationContactAndHoursFieldsets(),

                Fieldset::make('Staff')
                    ->schema([
                        AdminSelect::affiliatedDoctors(),
                        Select::make('primary_doctor_id')
                            ->label('Primary doctor (workplace)')
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->options(function (Get $get): array {
                                $ids = $get('doctor_ids') ?? [];

                                if ($ids === []) {
                                    return [];
                                }

                                return Doctor::query()
                                    ->whereIn('id', $ids)
                                    ->orderBy('full_name')
                                    ->pluck('full_name', 'id')
                                    ->all();
                            })
                            ->visible(fn (Get $get): bool => count($get('doctor_ids') ?? []) > 0)
                            ->columnSpanFull(),
                    ]),

                FacilityCommonForm::publishingFieldset(),
            ]);
    }
}
