<?php

namespace App\Support\Import\Pharmacies;

use App\Enums\ImportReviewKind;
use App\Models\Facility;
use App\Models\PharmacyDutyShift;
use App\Support\Import\ImportContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes one month of ФЗОМ's on-duty schedule (parsed by
 * OnDutyScheduleParser) into pharmacy_duty_shifts, replacing that month.
 *
 * Each row is linked to a directory pharmacy when its name (without „ПЗУ“,
 * „Аптека“, quotes and the town) and town match exactly one. A pharmacy that
 * matches none or several goes to the import review queue once — it is
 * never created: the schedule row still shows on „Каде веднаш“ under the
 * name ФЗОМ printed, just without a profile link. Rows whose date cannot be
 * read go to the queue too.
 *
 * Exception: while the directory has no published pharmacy in a town, its
 * unmatched pharmacies are only listed in the run report (diff.csv), not
 * queued — with no pharmacy import yet, every one of ФЗОМ's ~330 pharmacies
 * would otherwise land in one person's review queue every month.
 */
final class OnDutyPharmacyImporter
{
    public const SOURCE = 'on-duty-pharmacies';

    /**
     * @param  array{month: string, rows: list<array{line: int, town: string, municipality: string|null, name: string, days: list<int>, phone: string|null, mode: string, hours_text: string|null, address: string|null}>, problems: list<array{line: int, reason: string, text: string}>}  $parsed
     */
    public function import(ImportContext $context, array $parsed, ?string $sourceUrl = null): void
    {
        $month = $parsed['month'];
        $index = $this->pharmacyIndex();
        $coveredTowns = $this->townsWithPublishedPharmacies();
        $now = now();
        $shifts = [];
        $seen = [];

        $context->increment('rows_read', count($parsed['rows']) + count($parsed['problems']));

        foreach ($parsed['rows'] as $row) {
            $townKey = OnDutyNames::townKey($row['town']);
            $nameKey = OnDutyNames::pharmacyKey($row['name'], $row['town']);
            $candidates = $index[$townKey][$nameKey] ?? [];
            $facilityId = count($candidates) === 1 ? $candidates[0] : null;
            $pharmacy = $townKey.'|'.$nameKey;

            if (! isset($seen[$pharmacy])) {
                $seen[$pharmacy] = true;
                $context->increment('pharmacies_in_source');

                if ($facilityId !== null) {
                    $context->increment('pharmacies_matched');
                } elseif (count($candidates) === 0 && ! isset($coveredTowns[$townKey])) {
                    $context->increment('pharmacies_unmatched');
                    $context->increment('pharmacies_unmatched_not_queued');
                    $context->record('pharmacy', 'unmatched', null, $row['name'].', '.$row['town'], note: 'Not in the directory; no published pharmacy in this town yet, so not queued for review.');
                } else {
                    $ambiguous = count($candidates) > 1;
                    $context->increment($ambiguous ? 'pharmacies_ambiguous' : 'pharmacies_unmatched');
                    $context->review(
                        ImportReviewKind::Unmatched,
                        'on-duty-pharmacy:'.$townKey.':'.$nameKey,
                        ($ambiguous ? 'Several pharmacies match the on-duty pharmacy ' : 'On-duty pharmacy not in the directory: ').$row['name'].', '.$row['town'],
                        [
                            'reason' => $ambiguous ? 'ambiguous_pharmacy' : 'pharmacy_not_in_directory',
                            'name' => $row['name'],
                            'town' => $row['town'],
                            'municipality' => $row['municipality'],
                            'phone' => $row['phone'],
                            'candidate_facility_ids' => $candidates,
                            'source_url' => $sourceUrl,
                            'hint' => 'Create or rename the pharmacy profile so its name and town match; the next import links it.',
                        ],
                    );
                }
            }

            foreach ($row['days'] as $day) {
                $shifts[] = [
                    'month' => $month,
                    'duty_date' => CarbonImmutable::parse($month.'-01')->setDay($day)->toDateString(),
                    'town' => mb_substr($row['town'], 0, 64),
                    'town_key' => $townKey,
                    'municipality' => $row['municipality'] !== null ? mb_substr($row['municipality'], 0, 64) : null,
                    'pharmacy_name' => $row['name'],
                    'name_key' => $nameKey,
                    'facility_id' => $facilityId,
                    'phone' => $row['phone'],
                    'mode' => $row['mode'],
                    'hours_text' => $row['hours_text'],
                    'address' => $row['address'],
                    'import_run_id' => $context->run->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($parsed['problems'] as $problem) {
            $context->increment('rows_unreadable');
            $context->review(
                ImportReviewKind::Unmatched,
                'on-duty-row:'.$month.':'.sha1($problem['text']),
                'Unreadable on-duty schedule row ('.$month.', line '.$problem['line'].')',
                ['reason' => $problem['reason'], 'month' => $month, 'line' => $problem['line'], 'row' => $problem['text'], 'source_url' => $sourceUrl],
            );
        }

        if ($shifts === []) {
            throw new RuntimeException("No on-duty pharmacy could be read from the {$month} schedule; the file layout may have changed. Nothing was replaced.");
        }

        DB::transaction(function () use ($month, $shifts, $context): void {
            $removed = PharmacyDutyShift::query()->where('month', $month)->delete();

            foreach (array_chunk($shifts, 500) as $chunk) {
                DB::table('pharmacy_duty_shifts')->insert($chunk);
            }

            // A year of history is plenty; older months go.
            $cutoff = CarbonImmutable::parse($month.'-01')->subMonthsNoOverflow(12)->format('Y-m');
            $context->increment('shifts_expired', (int) PharmacyDutyShift::query()->where('month', '<', $cutoff)->delete());
            $context->increment('shifts_replaced', (int) $removed);
            $context->increment('shifts_written', count($shifts));
        });

        $context->record('schedule', 'replace', null, $month, 'shifts', null, (string) count($shifts), (string) $sourceUrl);
    }

    /**
     * Town keys with at least one published directory pharmacy.
     *
     * @return array<string, true>
     */
    private function townsWithPublishedPharmacies(): array
    {
        $towns = [];

        Facility::query()->pharmacy()->published()->whereNotNull('city')->pluck('city')
            ->each(function (string $city) use (&$towns): void {
                $towns[OnDutyNames::townKey($city)] = true;
            });

        return $towns;
    }

    /**
     * Directory pharmacies (drafts too: a link is internal until published)
     * by town key and name key.
     *
     * @return array<string, array<string, list<int>>>
     */
    private function pharmacyIndex(): array
    {
        $index = [];

        Facility::query()->pharmacy()->whereNotNull('city')->get(['id', 'name', 'city'])
            ->each(function (Facility $pharmacy) use (&$index): void {
                $town = (string) $pharmacy->city;
                $index[OnDutyNames::townKey($town)][OnDutyNames::pharmacyKey((string) $pharmacy->name, $town)][] = (int) $pharmacy->getKey();
            });

        return $index;
    }
}
