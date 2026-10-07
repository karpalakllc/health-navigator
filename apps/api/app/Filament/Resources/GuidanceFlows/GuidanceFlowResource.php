<?php

namespace App\Filament\Resources\GuidanceFlows;

use App\Filament\Resources\GuidanceFlows\Pages\ListGuidanceFlows;
use App\Filament\Resources\GuidanceFlows\Pages\ViewGuidanceFlow;
use App\Filament\Resources\GuidanceFlows\RelationManagers\VersionsRelationManager;
use App\Models\TriageFlow;
use App\Models\TriageFlowVersion;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Symptom-guidance flows v2 (docs/triage-flows.md). Content comes from the
 * flow files („Import from files“); here staff see each flow's versions,
 * step through them in the simulator, record a clinician's review and
 * publish. Nothing is edited in place: a change is a new file version.
 */
class GuidanceFlowResource extends Resource
{
    protected static ?string $model = TriageFlow::class;

    protected static ?string $slug = 'guidance-flows';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Guidance flows';

    protected static ?string $modelLabel = 'guidance flow';

    protected static string|\UnitEnum|null $navigationGroup = 'Guidance';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('key')->with(['latestVersion.latestReview', 'publishedVersion']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('key')->copyable(),
            TextEntry::make('title'),
            TextEntry::make('publishedVersion.version')->label('Published version')->placeholder('Not published — hidden from visitors'),
            TextEntry::make('summary')->label('Summary (internal)')
                ->state(fn (TriageFlow $record): string => (string) ($record->latestVersion?->definition['summary_en'] ?? ''))
                ->columnSpanFull(),
            TextEntry::make('audience')->label('Audience')
                ->state(fn (TriageFlow $record): string => implode(', ', $record->latestVersion?->definition['audience']['age_bands'] ?? [])),
            TextEntry::make('areas')->label('Body areas')
                ->state(fn (TriageFlow $record): string => implode(', ', $record->latestVersion?->definition['body_areas'] ?? [])),
            TextEntry::make('rank')->label('Urgency rank')
                ->state(fn (TriageFlow $record): string => (string) ($record->latestVersion?->definition['urgency_rank'] ?? '')),
            TextEntry::make('notes')->label('Notes (internal)')->placeholder('—')
                ->state(fn (TriageFlow $record): ?string => $record->latestVersion?->definition['notes_en'] ?? null)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('key')->searchable()->toggleable(),
                TextColumn::make('publishedVersion.version')->label('Live')->placeholder('—')->prefix('v'),
                TextColumn::make('latestVersion.version')->label('Latest')->prefix('v'),
                TextColumn::make('latestVersion.status')->label('Latest status')->badge()
                    ->color(fn (?string $state): string => self::statusColor($state)),
                TextColumn::make('review')->label('Clinician review')
                    ->state(fn (TriageFlow $record): string => self::reviewSummary($record->latestVersion))
                    ->wrap(),
                TextColumn::make('latestVersion.lint_warnings')->label('Lint warnings')->numeric(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            TriageFlowVersion::STATUS_PUBLISHED => 'success',
            TriageFlowVersion::STATUS_REVIEWED => 'info',
            TriageFlowVersion::STATUS_RETIRED => 'gray',
            default => 'warning',
        };
    }

    public static function reviewSummary(?TriageFlowVersion $version): string
    {
        if ($version === null) {
            return '—';
        }

        if ($version->review_exempt_reason !== null) {
            return 'Exempt (live before sign-off existed)';
        }

        $review = $version->latestReview;

        if ($review === null) {
            return 'нацрт — чека лекарски преглед';
        }

        return ($review->decision === 'approved' ? 'Approved' : 'Changes requested')
            .' on '.$review->reviewed_on->toDateString()
            .($review->reviewer_name ? ' by '.$review->reviewer_name : '');
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuidanceFlows::route('/'),
            'view' => ViewGuidanceFlow::route('/{record}'),
        ];
    }
}
