<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Pending)
                ->requiresConfirmation()
                ->action(fn (Review $record) => $record->approve(auth()->user())),
            Action::make('reject')
                ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Pending)
                ->form([
                    Textarea::make('rejection_note')
                        ->label('Rejection note (internal)')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(fn (Review $record, array $data) => $record->reject(
                    auth()->user(),
                    $data['rejection_note'] ?? null,
                )),
        ];
    }
}
