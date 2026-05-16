<?php

namespace App\Filament\Resources\Doctors\Schemas;

use App\Filament\Support\AdminSelect;
use App\Models\Facility;
use App\Models\Specialty;
use App\Support\OfficeHours;
use App\Support\PublicWebUrl;
use App\Support\Slug;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Fieldset::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Full name')
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->required()
                            ->maxLength(255)
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
                        TextInput::make('title')
                            ->label('Title')
                            ->prefixIcon(Heroicon::OutlinedAcademicCap)
                            ->placeholder('д-р')
                            ->maxLength(255),
                        TextInput::make('subspecialty')
                            ->label('Subspecialty')
                            ->prefixIcon(Heroicon::OutlinedSparkles)
                            ->maxLength(255),
                        Textarea::make('bio')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('avatar_url')
                            ->label('Avatar image URL')
                            ->prefixIcon(Heroicon::OutlinedPhoto)
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Fieldset::make('Professional profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('years_experience')
                            ->label('Years of experience')
                            ->prefixIcon(Heroicon::OutlinedClock)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(70),
                        TextInput::make('consultation_fee_note')
                            ->label('Consultation fee (display text)')
                            ->prefixIcon(Heroicon::OutlinedBanknotes)
                            ->maxLength(255)
                            ->placeholder('e.g. 2.500 – 4.500 МКД'),
                        Textarea::make('education')
                            ->rows(2)
                            ->columnSpanFull(),
                        AdminSelect::doctorSpecialties(),
                        Select::make('primary_specialty_id')
                            ->label('Primary specialty')
                            ->prefixIcon(Heroicon::OutlinedStar)
                            ->options(function (Get $get): array {
                                $ids = $get('specialty_ids') ?? [];

                                if ($ids === []) {
                                    return [];
                                }

                                return Specialty::query()
                                    ->whereIn('id', $ids)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->visible(fn (Get $get): bool => count($get('specialty_ids') ?? []) > 0)
                            ->columnSpanFull(),
                        AdminSelect::doctorLanguages(),
                        AdminSelect::doctorClinicalInterests(),
                        AdminSelect::doctorProcedures(),
                    ]),

                Fieldset::make('Schedule & availability')
                    ->schema([
                        Repeater::make('office_hours')
                            ->label('Office hours')
                            ->schema([
                                Select::make('day')
                                    ->label('Day')
                                    ->options(OfficeHours::DAY_OPTIONS)
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('hours')
                                    ->label('Hours')
                                    ->placeholder('08:00–14:00')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add day')
                            ->reorderable(false)
                            ->formatStateUsing(
                                fn ($state) => is_array($state) && array_is_list($state)
                                    ? $state
                                    : OfficeHours::toRows(is_array($state) ? $state : null),
                            )
                            ->dehydrateStateUsing(
                                fn ($state) => OfficeHours::fromRows(is_array($state) ? $state : null),
                            )
                            ->columnSpanFull(),
                        Toggle::make('accepts_new_patients')
                            ->label('Accepting new patients')
                            ->default(true),
                    ]),

                Fieldset::make('Contact & location')
                    ->columns(2)
                    ->schema([
                        TextInput::make('city')
                            ->prefixIcon(Heroicon::OutlinedMapPin)
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->prefixIcon(Heroicon::OutlinedPhone)
                            ->maxLength(50),
                        TextInput::make('email')
                            ->email()
                            ->prefixIcon(Heroicon::OutlinedEnvelope)
                            ->maxLength(255),
                        AdminSelect::affiliatedClinicalFacilities(),
                        Select::make('primary_facility_id')
                            ->label('Primary workplace')
                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                            ->options(function (Get $get): array {
                                $ids = $get('facility_ids') ?? [];

                                if ($ids === []) {
                                    return [];
                                }

                                return Facility::query()
                                    ->whereIn('id', $ids)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->visible(fn (Get $get): bool => count($get('facility_ids') ?? []) > 0)
                            ->columnSpanFull(),
                    ]),

                Fieldset::make('Publishing')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_featured')
                            ->label('Featured on home'),
                        Toggle::make('is_published')
                            ->default(false)
                            ->helperText(function ($record): ?string {
                                if (! $record?->is_published || ! PublicWebUrl::configured()) {
                                    return null;
                                }

                                $url = PublicWebUrl::forRecord($record);

                                return $url ? "Public: {$url}" : null;
                            }),
                    ]),
            ]);
    }
}
