<?php

namespace App\Filament\Support;

use App\Models\Facility;
use App\Support\OfficeHours;
use App\Support\UrgentCare\UrgentCareClassifier;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * „Каде веднаш“ fields on the facility form (docs/urgent-care.md § Data):
 * which urgent services a place offers, their hours and a direct line, and
 * the evidence the imports found. Saving with „Confirmed by staff“ set stops
 * the automatic deriver from changing the flags again.
 */
final class UrgentCareForm
{
    public const ED_STATUS_LABELS = [
        Facility::ED_CONFIRMED => 'Confirmed — runs an emergency department',
        Facility::ED_UNCONFIRMED_LIKELY => 'Likely, not confirmed (public general / clinical hospital)',
        Facility::ED_NONE => 'No emergency department (checked)',
    ];

    public const SERVICE_LABELS = [
        'has_emergency_medical_service' => 'Emergency medical service (служба за итна медицинска помош)',
        'has_on_duty_clinic' => 'On-duty clinic (дежурна амбуланта)',
        'has_dental_emergency' => 'Dental emergency (итна / дежурна стоматолошка служба)',
    ];

    public static function fieldset(): Fieldset
    {
        $toggles = [];

        foreach (self::SERVICE_LABELS as $column => $label) {
            $toggles[] = Toggle::make($column)->label($label);
        }

        return Fieldset::make('Urgent care („Каде веднаш“)')
            ->columns(2)
            ->schema([
                Select::make('emergency_department_status')
                    ->label('Emergency department (итно одделение / ургентен центар)')
                    ->options(self::ED_STATUS_LABELS)
                    ->placeholder('Unknown')
                    ->native(false)
                    ->helperText('„Likely“ is shown publicly as „итно одделение (непотврдено)“ with the main phone. Confirm or set „No“ once checked with the institution; the import never overrides your choice.')
                    ->columnSpanFull(),
                ...$toggles,
                Toggle::make('is_open_24h')
                    ->label('Urgent service open 24/7')
                    ->helperText('Only when the institution itself says so. Otherwise enter the hours below, or leave them empty: the site then says the hours are not confirmed.')
                    ->columnSpanFull(),
                Repeater::make('emergency_hours')
                    ->label('Urgent service hours')
                    ->schema([
                        Select::make('day')
                            ->label('Day')
                            ->options(OfficeHours::DAY_OPTIONS)
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('hours')
                            ->label('Hours')
                            ->placeholder('07:00–20:00')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add day')
                    ->reorderable(false)
                    ->formatStateUsing(
                        fn ($state) => is_array($state) && array_is_list($state)
                            ? $state
                            : OfficeHours::toRows(is_array($state) ? $state : null),
                    )
                    // Rows → {day: hours} happens in Facility::emergencyHours():
                    // the repeater re-keys a dehydrated map into a list,
                    // which would drop the day labels.
                    ->columnSpanFull(),
                TextInput::make('emergency_phone')
                    ->label('Direct urgent line')
                    ->tel()
                    ->prefixIcon(Heroicon::OutlinedPhone)
                    ->maxLength(50),
                TextInput::make('urgent_care_note')
                    ->label('Public note')
                    ->placeholder('Влез од ул. …')
                    ->maxLength(255),
                DateTimePicker::make('urgent_care_checked_at')
                    ->label('Confirmed by staff')
                    ->helperText('Set when you checked these fields with the institution. The import then never changes them again.')
                    ->seconds(false),
                TextEntry::make('urgent_care_evidence_summary')
                    ->label('Evidence from the imports')
                    ->state(fn (?Facility $record): HtmlString => self::evidenceHtml($record))
                    ->columnSpanFull(),
            ]);
    }

    public static function evidenceHtml(?Facility $record): HtmlString
    {
        $evidence = $record?->urgent_care_evidence;
        $items = is_array($evidence) ? (array) ($evidence['items'] ?? []) : [];

        if ($items === []) {
            return new HtmlString('<span style="opacity:.7">None found.</span>');
        }

        $flags = [
            UrgentCareClassifier::FLAG_ED => 'Emergency department',
            UrgentCareClassifier::FLAG_EMS => 'Emergency medical service',
            UrgentCareClassifier::FLAG_CLINIC => 'On-duty clinic',
            UrgentCareClassifier::FLAG_DENTAL => 'Dental emergency',
            UrgentCareClassifier::FLAG_OPEN_24H => '24/7',
        ];
        $lines = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lines[] = sprintf(
                '<li><strong>%s</strong> — %s (%s, %s): „%s“</li>',
                e($flags[$item['flag'] ?? ''] ?? (string) ($item['flag'] ?? '')),
                ($item['strength'] ?? '') === UrgentCareClassifier::STRONG ? 'strong' : 'to check',
                e((string) ($item['source'] ?? '')),
                e(str_replace('_', ' ', (string) ($item['kind'] ?? ''))),
                e((string) ($item['text'] ?? '')),
            );
        }

        $derived = (array) ($evidence['derived'] ?? []);
        $footer = $derived !== []
            ? '<p style="opacity:.7;margin-top:.25rem">Switched on automatically: '.e(implode(', ', array_map(fn ($flag): string => $flags[$flag] ?? (string) $flag, $derived))).'. Switching one off keeps it off.</p>'
            : '';

        return new HtmlString('<ul style="list-style:disc;padding-left:1.25rem">'.implode('', $lines).'</ul>'.$footer);
    }

    /**
     * Facilities with the urgent service named (ed | ems | clinic | dental), or any.
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public static function scopeService(Builder $query, ?string $service): Builder
    {
        return Facility::whereUrgentCare($query, $service);
    }

    /**
     * „To check“: imported evidence staff have not confirmed yet.
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public static function scopeToCheck(Builder $query): Builder
    {
        return $query->whereNotNull('urgent_care_evidence')
            ->whereNull('urgent_care_checked_at');
    }
}
