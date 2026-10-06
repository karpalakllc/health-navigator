<?php

namespace App\Filament\Support;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\User;
use App\Support\Import\ImportReviewActions;
use App\Support\Import\ProvenanceWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * "Import locks" on the doctor and facility edit pages: which fields
 * imports may no longer change, with where each value came from. A lock
 * survives every re-import; unticking it lets the source update the field
 * again (it raises a conflict first if the value was edited by hand).
 */
final class ImportLockAction
{
    public static function make(): Action
    {
        return Action::make('importLocks')
            ->label('Import locks')
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('imports.manage') ?? false)
            ->fillForm(fn (Doctor|Facility $record): array => [
                'locked' => self::rows($record)->where('locked', true)->pluck('field')->values()->all(),
            ])
            ->schema(fn (Doctor|Facility $record): array => [
                CheckboxList::make('locked')
                    ->label('Locked against imports')
                    ->options(array_combine(ImportReviewActions::lockableFields($record), ImportReviewActions::lockableFields($record)))
                    ->descriptions(self::descriptions($record))
                    ->columns(1),
            ])
            ->action(function (Doctor|Facility $record, array $data): void {
                $user = auth()->user();
                $wanted = array_map('strval', (array) ($data['locked'] ?? []));

                foreach (ImportReviewActions::lockableFields($record) as $field) {
                    $isLocked = (bool) self::rows($record)->firstWhere('field', $field)?->locked;
                    $lock = in_array($field, $wanted, true);

                    if ($isLocked !== $lock) {
                        ProvenanceWriter::setLock($record, $field, $lock, $user instanceof User ? $user : null);
                    }
                }

                Notification::make()->title('Import locks saved')->success()->send();
            });
    }

    /**
     * @return Collection<int, FieldProvenance>
     */
    private static function rows(Doctor|Facility $record): Collection
    {
        return FieldProvenance::query()
            ->where('subject_type', ProvenanceWriter::entity($record))
            ->where('subject_id', $record->getKey())
            ->get()
            ->toBase();
    }

    /**
     * @return array<string, string>
     */
    private static function descriptions(Doctor|Facility $record): array
    {
        $descriptions = [];

        foreach (self::rows($record) as $row) {
            $parts = array_filter([
                $row->source !== null ? 'from '.$row->source : null,
                $row->observed_at?->format('d.m.Y'),
                $row->source_url,
                $row->locked ? 'locked by '.($row->lockedBy?->name ?? 'staff') : null,
            ]);
            $descriptions[$row->field] = implode(' · ', $parts);
        }

        return $descriptions;
    }
}
