<?php

namespace App\Filament\Resources\DoctorReplies;

use App\Enums\ReviewResponseSource;
use App\Enums\ReviewResponseStatus;
use App\Filament\Resources\DoctorReplies\Pages\ListDoctorReplies;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Support\ReviewableLabel;
use App\Filament\Support\ReviewResponseActions;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queue of replies linked doctors wrote under reviews of their own
 * profile. Pre-moderated while doctor_replies_require_moderation is on (the
 * default): check the reply reveals or confirms nothing about a patient.
 * Same rights as the official response (reviews.respond).
 */
class DoctorReplyResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'doctor-replies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Doctor replies';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'doctor reply';

    protected static ?string $pluralModelLabel = 'doctor replies';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('reviews.respond') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('response_body')
            ->where('response_source', ReviewResponseSource::Doctor);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Review::query()->withPendingDoctorReply()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('response_status')
                    ->label('Reply')
                    ->badge()
                    ->formatStateUsing(fn (?ReviewResponseStatus $state): string => ucfirst($state->value ?? '—'))
                    ->color(fn (?ReviewResponseStatus $state): string => match ($state) {
                        ReviewResponseStatus::Pending => 'warning',
                        ReviewResponseStatus::Approved => 'success',
                        ReviewResponseStatus::Rejected => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reviewable_label')
                    ->label('Doctor')
                    ->state(fn (Review $record): string => ReviewableLabel::forReview($record))
                    ->wrap(),
                TextColumn::make('body')
                    ->label('Review')
                    ->limit(120)
                    ->wrap(),
                TextColumn::make('response_body')
                    ->label('Doctor reply')
                    ->limit(200)
                    ->wrap(),
                TextColumn::make('response_at')
                    ->label('Written')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('response_at', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reviewable']))
            ->filters([
                SelectFilter::make('response_status')
                    ->label('Reply status')
                    ->options(collect(ReviewResponseStatus::cases())->mapWithKeys(
                        fn (ReviewResponseStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(ReviewResponseStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('openReview')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Review $record): string => ReviewResource::getUrl('view', ['record' => $record])),
                ReviewResponseActions::approveDoctorReply(),
                ReviewResponseActions::rejectDoctorReply(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorReplies::route('/'),
        ];
    }
}
