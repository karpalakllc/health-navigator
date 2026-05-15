<?php

namespace App\Filament\Resources\Doctors\Schemas;

use App\Filament\Support\AdminSelect;
use App\Filament\Support\CommaSeparatedList;
use App\Models\Facility;
use App\Models\Specialty;
use App\Support\PublicWebUrl;
use App\Support\Slug;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if (filled($state)) {
                            $set('slug', Slug::fromName($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('title')
                    ->maxLength(255),
                TextInput::make('subspecialty')
                    ->maxLength(255),
                Textarea::make('bio')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('years_experience')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(70),
                Textarea::make('education')
                    ->rows(2)
                    ->columnSpanFull(),
                CommaSeparatedList::make('languages', 'Languages'),
                CommaSeparatedList::make('clinical_interests', 'Clinical interests / conditions'),
                CommaSeparatedList::make('procedures', 'Procedures'),
                TextInput::make('consultation_fee_note')
                    ->label('Consultation fee (display text)')
                    ->maxLength(255)
                    ->placeholder('e.g. 2.500 – 4.500 МКД'),
                TextInput::make('avatar_url')
                    ->label('Avatar image URL')
                    ->url()
                    ->maxLength(500)
                    ->columnSpanFull(),
                Textarea::make('office_hours')
                    ->label('Office hours (JSON)')
                    ->rows(4)
                    ->helperText('e.g. {"Пон":"08:00–14:00","Вто":"08:00–14:00"}')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                    ->dehydrateStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state;
                        }

                        if (! filled($state)) {
                            return null;
                        }

                        return json_decode((string) $state, true, 512, JSON_THROW_ON_ERROR);
                    })
                    ->columnSpanFull(),
                Toggle::make('accepts_new_patients')
                    ->default(true),
                Toggle::make('is_featured')
                    ->label('Featured on home'),
                TextInput::make('city')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Toggle::make('is_published')
                    ->default(false)
                    ->helperText(function ($record): ?string {
                        if (! $record?->is_published || ! PublicWebUrl::configured()) {
                            return null;
                        }

                        $url = PublicWebUrl::forRecord($record);

                        return $url ? "Public: {$url}" : null;
                    }),
                AdminSelect::affiliatedClinicalFacilities(),
                Select::make('primary_facility_id')
                    ->label('Primary workplace')
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
                CheckboxList::make('specialty_ids')
                    ->label('Specialties')
                    ->options(fn (): array => Specialty::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->columns(2)
                    ->columnSpanFull(),
                Select::make('primary_specialty_id')
                    ->label('Primary specialty')
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
            ]);
    }
}
