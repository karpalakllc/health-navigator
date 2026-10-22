<?php

namespace App\Filament\Resources\Pharmacies\Pages;

use App\Filament\Concerns\HasPublicPreviewAction;
use App\Filament\Resources\Pharmacies\Concerns\SetsPharmacyDefaults;
use App\Filament\Resources\Pharmacies\PharmacyResource;
use App\Filament\Support\VerificationActions;
use App\Models\Facility;
use App\Models\User;
use App\Support\Import\Pharmacies\OnDutyLinker;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;

class EditPharmacy extends EditRecord
{
    use HasPublicPreviewAction;
    use SetsPharmacyDefaults;

    protected static string $resource = PharmacyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getPublicPreviewAction(),
            $this->unlinkOnDutyAction(),
            ...VerificationActions::all(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * The on-duty schedule (ФЗОМ's monthly list) of a pharmacy linked to the
     * wrong directory pharmacy: unlinks it for every month, forgets the
     * staff link and remembers „not this pharmacy“ so the next import does
     * not link it again. Optionally links the right pharmacy at once;
     * otherwise the schedule pharmacy is queued in Import review (“Link to
     * pharmacy”).
     */
    protected function unlinkOnDutyAction(): Action
    {
        return Action::make('unlinkOnDuty')
            ->label('Unlink on-duty schedule')
            ->icon('heroicon-o-link-slash')
            ->color('warning')
            ->visible(fn (): bool => (auth()->user()?->can('imports.manage') ?? false) && app(OnDutyLinker::class)->linkedSchedules($this->facility()) !== [])
            ->schema(function (): array {
                $linker = app(OnDutyLinker::class);
                $schedules = $linker->linkedSchedules($this->facility());

                return [
                    Select::make('schedule')
                        ->label('On-duty schedule pharmacy')
                        ->options($schedules)
                        ->default(count($schedules) === 1 ? array_key_first($schedules) : null)
                        ->live()
                        ->required(),
                    Select::make('link_to')
                        ->label('Link to another pharmacy instead (optional)')
                        ->options(fn (Get $get): array => collect($linker->optionsForTown(explode('|', (string) $get('schedule'))[0]))
                            ->except([(int) $this->facility()->getKey()])->all())
                        ->searchable()
                        ->helperText('Leave empty to let the import find the right pharmacy, or to queue it in Import review.'),
                ];
            })
            ->modalDescription('Unlinks this on-duty schedule pharmacy from every month, forgets a staff link and stops the import from linking this pair again.')
            ->modalSubmitActionLabel('Unlink')
            ->action(function (array $data): void {
                [$townKey, $nameKey] = array_pad(explode('|', (string) $data['schedule'], 2), 2, '');
                $user = auth()->user();

                if (! $user instanceof User || $townKey === '' || $nameKey === '') {
                    return;
                }

                $result = app(OnDutyLinker::class)->unlinkByStaff($this->facility(), $townKey, $nameKey, $user, is_numeric($data['link_to'] ?? null) ? (int) $data['link_to'] : null);

                Notification::make()
                    ->title($result['relinked_to'] !== null ? 'Unlinked, and linked to another pharmacy.' : 'Unlinked; the schedule pharmacy is queued in Import review.')
                    ->body($result['shifts'].' duty shifts unlinked.')
                    ->success()->send();
            });
    }

    private function facility(): Facility
    {
        /** @var Facility $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->applyPharmacyDefaults($data);
    }
}
