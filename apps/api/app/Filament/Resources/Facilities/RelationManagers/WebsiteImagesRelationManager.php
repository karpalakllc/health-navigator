<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Models\User;
use App\Support\Media\MediaUrl;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Images imported from the institution's website, each with its source URL:
 * switch the cover to another candidate, or take an image down in one
 * click (file deleted, recorded in the activity log, never re-imported).
 */
class WebsiteImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Website images';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Facility && $ownerRecord->media()->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->state(fn (FacilityMedia $record): ?string => MediaUrl::resolve($record->path))
                    ->height(48),
                TextColumn::make('kind')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        FacilityMedia::STATUS_REMOVED, FacilityMedia::STATUS_REPLACED => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('in_use')
                    ->label('Shown')
                    ->state(fn (FacilityMedia $record): string => $record->isCurrent() ? 'yes' : '—'),
                TextColumn::make('source_url')
                    ->label('Source')
                    ->url(fn (FacilityMedia $record): ?string => $record->source_url, shouldOpenInNewTab: true)
                    ->limit(50),
                TextColumn::make('removed_at')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('useAsCover')
                    ->label('Use as cover')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (FacilityMedia $record): bool => self::canManage() && $record->kind === FacilityMedia::KIND_COVER && ! $record->isRemoved() && $record->path !== null && ! $record->isCurrent())
                    ->action(function (FacilityMedia $record): void {
                        $record->makeCurrentCover(self::actor());
                        Notification::make()->title('Cover changed')->success()->send();
                    }),
                Action::make('remove')
                    ->label('Remove image')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (FacilityMedia $record): bool => self::canManage() && ! $record->isRemoved())
                    ->requiresConfirmation()
                    ->modalDescription('Deletes the file now and keeps it out of future imports. Use for any takedown request.')
                    ->action(function (FacilityMedia $record): void {
                        $record->remove(self::actor());
                        Notification::make()->title('Image removed')->success()->send();
                    }),
            ]);
    }

    private static function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->can('facilities.update') || $user->can('imports.manage'));
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
