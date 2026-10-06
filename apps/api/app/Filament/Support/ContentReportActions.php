<?php

namespace App\Filament\Support;

use App\Enums\ReportStatus;
use App\Models\ContentReport;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolve actions for the report queue, shared by the table rows and the view
 * page. Both resolve every open report on the same item at once.
 */
final class ContentReportActions
{
    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('Hide content')
            ->color('danger')
            ->icon('heroicon-o-eye-slash')
            ->visible(fn (ContentReport $record): bool => self::canHide($record))
            ->modalDescription('Unpublishes the reported content and closes every open report on it. The author is emailed the note below.')
            ->form([
                Textarea::make('note')
                    ->label('Note to the author (optional)')
                    ->helperText('Sent to the author in the removal email and shown in their account. Write it in Macedonian. Left empty, a neutral default is used.')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function (ContentReport $record, array $data): void {
                // Re-checked at execution: visibility alone is not authorization.
                if (! self::canHide($record)) {
                    return;
                }

                $record->hideContent(self::actor(), $data['note'] ?? null);

                Notification::make()->title('Content hidden')->success()->send();
            });
    }

    public static function keep(): Action
    {
        return Action::make('keep')
            ->label('Keep content')
            ->color('gray')
            ->icon('heroicon-o-check')
            ->visible(fn (ContentReport $record): bool => self::canResolve($record))
            ->modalDescription('Leaves the content published and closes every open report on it.')
            ->requiresConfirmation()
            ->action(function (ContentReport $record): void {
                if (! self::canResolve($record)) {
                    return;
                }

                $record->keepContent(self::actor());

                Notification::make()->title('Reports closed; content kept')->success()->send();
            });
    }

    private static function canResolve(ContentReport $record): bool
    {
        return $record->status === ReportStatus::Open
            && (auth()->user()?->can('update', $record) ?? false);
    }

    /**
     * Hiding changes the content itself, so it also needs the right to
     * moderate that content (reviews.update, or forum moderation in scope).
     */
    private static function canHide(ContentReport $record): bool
    {
        $content = $record->reportable;

        return self::canResolve($record)
            && $content instanceof Model
            && (auth()->user()?->can('update', $content) ?? false);
    }

    private static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
