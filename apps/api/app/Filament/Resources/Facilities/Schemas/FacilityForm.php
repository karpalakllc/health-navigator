<?php

namespace App\Filament\Resources\Facilities\Schemas;

use App\Enums\FacilityType;
use App\Filament\Support\AdminSelect;
use App\Models\Doctor;
use App\Support\PublicWebUrl;
use App\Support\Slug;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class FacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
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
                Select::make('type')
                    ->options(collect(FacilityType::cases())->mapWithKeys(
                        fn (FacilityType $type) => [$type->value => ucfirst($type->value)],
                    )->all())
                    ->required()
                    ->native(false),
                Textarea::make('description')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('city')
                    ->maxLength(255),
                TextInput::make('address')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('website')
                    ->url()
                    ->maxLength(500),
                TextInput::make('avatar_url')
                    ->label('Image URL')
                    ->url()
                    ->maxLength(500)
                    ->columnSpanFull(),
                Textarea::make('office_hours')
                    ->label('Opening hours (JSON)')
                    ->rows(4)
                    ->helperText('e.g. {"Пон–Пет":"08:00–18:00"}')
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
                Toggle::make('is_published')
                    ->default(false)
                    ->helperText(function ($record): ?string {
                        if (! $record?->is_published || ! PublicWebUrl::configured()) {
                            return null;
                        }

                        $url = PublicWebUrl::forRecord($record);

                        return $url ? "Public: {$url}" : null;
                    }),
                AdminSelect::affiliatedDoctors(),
                Select::make('primary_doctor_id')
                    ->label('Primary doctor (workplace)')
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
            ]);
    }
}
