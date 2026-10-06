<?php

namespace App\Filament\Resources\ActivityLog;

use App\Filament\Resources\ActivityLog\Pages\ListActivities;
use App\Filament\Resources\ActivityLog\Pages\ViewActivity;
use App\Models\Activity;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * „Дневник на активности“: the read-only audit log (config/activitylog.php)
 * — doctor-profile edits and featured/sponsored toggles, linked-doctor
 * actions, change-request and reply decisions, report resolutions, account
 * suspensions — with who did each. Kept 365 days. Needs audit.view.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activity-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Activity log';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    /** @var array<string, string> */
    private const LOGS = [
        'doctor_profile' => 'Doctor profiles',
        'doctor_accounts' => 'Doctor accounts',
        'reviews' => 'Reviews and replies',
        'reports' => 'Reports',
        'accounts' => 'Accounts',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function subjectLabel(Activity $record): string
    {
        if ($record->subject_type === null) {
            return '—';
        }

        $subject = $record->subject;
        $name = $subject instanceof Model ? ($subject->getAttribute('full_name') ?? $subject->getAttribute('email')) : null;

        return class_basename($record->subject_type).' #'.$record->subject_id.(is_string($name) && $name !== '' ? ' · '.$name : '');
    }

    public static function causerLabel(Activity $record): string
    {
        $causer = $record->causer;

        return $causer instanceof User ? $causer->name.' <'.$causer->email.'>' : 'System';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('log_name')
                ->label('Area')
                ->formatStateUsing(fn (?string $state): string => self::LOGS[$state] ?? (string) $state),
            TextEntry::make('event')->placeholder('—'),
            TextEntry::make('description'),
            TextEntry::make('subject_label')
                ->label('Subject')
                ->state(fn (Activity $record): string => self::subjectLabel($record)),
            TextEntry::make('causer_label')
                ->label('By')
                ->state(fn (Activity $record): string => self::causerLabel($record)),
            TextEntry::make('changes_json')
                ->label('Changes (old → new)')
                ->state(fn (Activity $record): string => self::json($record->attribute_changes?->toArray()))
                ->fontFamily('mono')
                ->columnSpanFull(),
            TextEntry::make('properties_json')
                ->label('Details')
                ->state(fn (Activity $record): string => self::json($record->properties?->toArray()))
                ->fontFamily('mono')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Area')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::LOGS[$state] ?? (string) $state),
                TextColumn::make('event')
                    ->placeholder('—'),
                TextColumn::make('subject_label')
                    ->label('Subject')
                    ->state(fn (Activity $record): string => self::subjectLabel($record))
                    ->wrap(),
                TextColumn::make('changed')
                    ->label('Changed')
                    ->state(fn (Activity $record): string => implode(', ', array_keys((array) ($record->attribute_changes?->get('attributes') ?? $record->attribute_changes?->get('old') ?? []))))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('causer_label')
                    ->label('By')
                    ->state(fn (Activity $record): string => self::causerLabel($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['causer', 'subject']))
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Area')
                    ->options(self::LOGS),
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                        'relations_updated' => 'Links updated',
                        'kept' => 'Report: kept',
                        'hidden' => 'Report: hidden',
                        'suspended' => 'Suspended',
                        'unsuspended' => 'Suspension lifted',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * @param  array<mixed>|null  $value
     */
    private static function json(?array $value): string
    {
        if ($value === null || $value === []) {
            return '—';
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
