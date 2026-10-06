<?php

namespace App\Filament\Resources\ContentReports\Tables;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Support\ContentReportActions;
use App\Filament\Support\ReportedContentLabel;
use App\Models\ContentReport;
use App\Models\ForumPost;
use App\Models\Review;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class ContentReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => ucfirst($state->value))
                    ->color(fn (ReportStatus $state): string => match ($state) {
                        ReportStatus::Open => 'warning',
                        ReportStatus::Kept => 'gray',
                        ReportStatus::Hidden => 'danger',
                    }),
                TextColumn::make('reason')
                    ->formatStateUsing(fn (ReportReason $state): string => $state->label()),
                TextColumn::make('target')
                    ->label('Reported content')
                    ->state(fn (ContentReport $record): string => ReportedContentLabel::target($record))
                    ->description(fn (ContentReport $record): string => str(ReportedContentLabel::body($record))->limit(90)->toString())
                    ->wrap(),
                TextColumn::make('note')
                    ->label('Reporter note')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('open_reports')
                    ->label('Open reports on item')
                    ->state(fn (ContentReport $record): int => $record->openReportsOnSameContent())
                    ->alignCenter(),
                TextColumn::make('user.name')
                    ->label('Reporter')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resolvedBy.name')
                    ->label('Resolved by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user',
                'resolvedBy',
                'reportable' => function (Relation $relation): void {
                    if ($relation instanceof MorphTo) {
                        $relation->morphWith([
                            Review::class => ['reviewable'],
                            ForumPost::class => ['topic.category'],
                        ]);
                    }
                },
            ]))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ReportStatus::cases())->mapWithKeys(
                        fn (ReportStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(ReportStatus::Open->value),
                SelectFilter::make('reason')
                    ->options(collect(ReportReason::cases())->mapWithKeys(
                        fn (ReportReason $reason) => [$reason->value => $reason->label()],
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ContentReportActions::hide(),
                ContentReportActions::keep(),
            ]);
    }
}
