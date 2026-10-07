<?php

namespace App\Filament\Resources\GuidanceFlows\RelationManagers;

use App\Filament\Pages\GuidanceSimulator;
use App\Filament\Resources\GuidanceFlows\GuidanceFlowResource;
use App\Models\TriageFlowReview;
use App\Models\TriageFlowVersion;
use App\Models\User;
use App\Services\Triage\V2\FlowPublication;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Version history of a flow, with the clinician sign-off and publication
 * workflow: draft → review recorded (approved) → published.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Versions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->modifyQueryUsing(fn ($query) => $query->with(['latestReview', 'publisher']))
            ->columns([
                TextColumn::make('version')->prefix('v')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => GuidanceFlowResource::statusColor($state)),
                TextColumn::make('review')->label('Clinician review')
                    ->state(fn (TriageFlowVersion $record): string => GuidanceFlowResource::reviewSummary($record))
                    ->wrap(),
                TextColumn::make('lint_warnings')->label('Lint warnings')->numeric(),
                TextColumn::make('published_at')->dateTime()->placeholder('—'),
                TextColumn::make('created_at')->label('Imported')->dateTime(),
            ])
            ->recordActions([
                Action::make('simulate')
                    ->label('Simulate')
                    ->icon('heroicon-o-play')
                    ->url(fn (TriageFlowVersion $record): string => GuidanceSimulator::getUrl(['version' => $record->id])),
                Action::make('lint')
                    ->label('Lint report')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (TriageFlowVersion $record): HtmlString => $this->lintReportHtml($record)),
                Action::make('review')
                    ->label('Record clinician review')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->authorize(fn (): bool => $this->canManage())
                    ->visible(fn (TriageFlowVersion $record): bool => $record->status !== TriageFlowVersion::STATUS_RETIRED)
                    ->modalDescription('Record what a clinician concluded about THIS version (stepped through in the simulator or on the printed checklist). The name and registration number are optional; the date and note are not.')
                    ->schema([
                        Radio::make('decision')
                            ->options([
                                TriageFlowReview::DECISION_APPROVED => 'Approved for publication',
                                TriageFlowReview::DECISION_CHANGES_REQUESTED => 'Changes requested',
                            ])
                            ->required(),
                        TextInput::make('reviewer_name')->label('Clinician name (optional)')->maxLength(120),
                        TextInput::make('reviewer_registration')->label('Registration / licence no. (optional)')->maxLength(64),
                        DatePicker::make('reviewed_on')->label('Reviewed on')->required()->maxDate(now())->default(now()),
                        Textarea::make('note')->label('Review note')->required()->minLength(5)->maxLength(4000)->rows(4),
                    ])
                    ->action(function (TriageFlowVersion $record, array $data, FlowPublication $publication): void {
                        $publication->recordReview($record, $data, $this->staff());
                        Notification::make()->title('Review recorded')->success()->send();
                    }),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->authorize(fn (): bool => $this->canManage())
                    ->visible(fn (TriageFlowVersion $record): bool => ! $record->isPublished())
                    ->requiresConfirmation()
                    ->modalDescription(fn (TriageFlowVersion $record): string => app(FlowPublication::class)->blocker($record)
                        ?? 'Visitors will get this version from now on; the currently published version (if any) is retired. Sessions already running keep the version they started with.')
                    ->action(function (TriageFlowVersion $record, FlowPublication $publication): void {
                        try {
                            $publication->publish($record, $this->staff());
                            Notification::make()->title("v{$record->version} published")->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title('Not published')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                        }
                    }),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->authorize(fn (): bool => $this->canManage())
                    ->visible(fn (TriageFlowVersion $record): bool => $record->isPublished())
                    ->requiresConfirmation()
                    ->modalDescription('The flow disappears from public guidance until a version is published again.')
                    ->action(function (TriageFlowVersion $record, FlowPublication $publication): void {
                        $publication->unpublish($record);
                        Notification::make()->title('Unpublished')->success()->send();
                    }),
            ]);
    }

    private function canManage(): bool
    {
        return $this->staff()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    private function staff(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function lintReportHtml(TriageFlowVersion $record): HtmlString
    {
        $report = app(FlowPublication::class)->lint($record);
        $rows = [];

        foreach ($report->errors as $error) {
            $rows[] = '<li><strong>error</strong> '.e($error).'</li>';
        }

        foreach ($report->warnings as $warning) {
            $rows[] = '<li>warning '.e($warning).'</li>';
        }

        $reviews = $record->reviews()->with('recorder')->get()->map(fn (TriageFlowReview $r) => '<li>'
            .e($r->reviewed_on->toDateString().' — '.$r->decision.($r->reviewer_name ? ' — '.$r->reviewer_name : '').($r->reviewer_registration ? ' ('.$r->reviewer_registration.')' : ''))
            .'<br>'.e($r->note).'<br><small>recorded by '.e($r->recorder->name ?? '—').'</small></li>')->implode('');

        return new HtmlString(
            '<p>Re-checked now against the current global screen.</p>'
            .($rows === [] ? '<p>No errors or warnings.</p>' : '<ul style="list-style:disc;padding-left:1.25rem;display:grid;gap:.25rem">'.implode('', $rows).'</ul>')
            .'<h3 style="margin-top:1rem;font-weight:600">Review history</h3>'
            .($reviews === '' ? '<p>None recorded.</p>' : '<ul style="display:grid;gap:.5rem">'.$reviews.'</ul>')
            .'<p style="margin-top:1rem">Source file: '.e((string) $record->source_path).'</p>'
        );
    }
}
