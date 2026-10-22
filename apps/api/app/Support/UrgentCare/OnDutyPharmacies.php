<?php

namespace App\Support\UrgentCare;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\PharmacyDutyShift;
use App\Models\SiteSetting;
use App\Support\Import\Pharmacies\OnDutyNames;
use App\Support\Import\TextCase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who is on duty now, from the imported ФЗОМ schedule (docs/urgent-care.md
 * § On-duty pharmacies). „Now“ is PharmacyDutyShift::currentDutyDate():
 * before 07:00 Skopje time the previous night's list still applies.
 */
final class OnDutyPharmacies
{
    public const SOURCE_URL = 'https://fzo.org.mk/dezurni-apteki';

    private const MODE_ORDER = [
        PharmacyDutyShift::MODE_ALL_DAY => 0,
        PharmacyDutyShift::MODE_HOURS => 1,
        PharmacyDutyShift::MODE_ON_CALL => 2,
        PharmacyDutyShift::MODE_UNKNOWN => 3,
    ];

    /**
     * The `meta.on_duty_pharmacies` block of GET /urgent-care. Items only for
     * a chosen city; `available` says whether the schedule for the month is
     * imported at all (else the site shows its placeholder).
     *
     * @return array{available: bool, date: string, source_url: string, items: list<array<string, mixed>>|null}
     */
    public static function forCity(?string $city, ?CarbonImmutable $now = null): array
    {
        $date = PharmacyDutyShift::currentDutyDate($now);
        $available = PharmacyDutyShift::query()->where('month', $date->format('Y-m'))->exists();
        $items = null;

        if ($available && $city !== null && trim($city) !== '') {
            $profiles = (bool) SiteSetting::current()->public_pharmacies;
            $items = PharmacyDutyShift::query()
                ->onDate($date)
                ->where('town_key', OnDutyNames::townKey($city))
                ->with('facility')
                ->get()
                ->map(fn (PharmacyDutyShift $shift): array => self::item($shift, $profiles))
                ->sortBy(fn (array $item): string => self::rank($item['mode']).'|'.mb_strtolower($item['name']))
                ->values()
                ->all();
        }

        return [
            'available' => $available,
            'date' => $date->toDateString(),
            'source_url' => self::SOURCE_URL,
            'items' => $items,
        ];
    }

    /**
     * „Дежурна денес“ on a pharmacy profile.
     *
     * @return array{date: string, mode: string, hours_text: string|null}|null
     */
    public static function forPharmacy(Facility $pharmacy, ?CarbonImmutable $now = null): ?array
    {
        $id = (int) $pharmacy->getKey();

        return self::forPharmacies([$id], $now)[$id] ?? null;
    }

    /**
     * „Дежурна денес“ for a page of pharmacies (directory list, search
     * vertical) in one query, keyed by facility id. A pharmacy listed twice
     * on one day keeps its most available shift (24 h before on call).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{date: string, mode: string, hours_text: string|null}>
     */
    public static function forPharmacies(array $ids, ?CarbonImmutable $now = null): array
    {
        if ($ids === []) {
            return [];
        }

        $date = PharmacyDutyShift::currentDutyDate($now);
        $duties = [];

        $shifts = PharmacyDutyShift::query()
            ->onDate($date)
            ->whereIn('facility_id', array_values(array_unique($ids)))
            ->orderBy('id')
            ->get(['facility_id', 'mode', 'hours_text']);

        foreach ($shifts as $shift) {
            $id = (int) $shift->facility_id;
            $current = $duties[$id] ?? null;

            if ($current === null || self::rank($shift->mode) < self::rank($current['mode'])) {
                $duties[$id] = [
                    'date' => $date->toDateString(),
                    'mode' => $shift->mode,
                    'hours_text' => $shift->hours_text,
                ];
            }
        }

        return $duties;
    }

    /**
     * Only pharmacies on duty now (`on_duty=1` on GET /pharmacies).
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public static function whereOnDuty(Builder $query, ?CarbonImmutable $now = null): Builder
    {
        return $query->whereExists(self::shiftsOf($now)->select(DB::raw('1'))->toBase());
    }

    /**
     * On-duty pharmacies first — 24 h, then set hours, then on call — ahead
     * of the list's own order. One correlated subquery on the
     * (facility_id, duty_date) index; pharmacies not on duty rank last.
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public static function orderOnDutyFirst(Builder $query, ?CarbonImmutable $now = null): Builder
    {
        $cases = '';
        $bindings = [];

        foreach (self::MODE_ORDER as $mode => $rank) {
            $cases .= ' WHEN ? THEN '.$rank;
            $bindings[] = $mode;
        }

        $notOnDuty = count(self::MODE_ORDER) + 1;
        $rank = self::shiftsOf($now)->selectRaw('MIN(CASE pharmacy_duty_shifts.mode'.$cases.' ELSE '.count(self::MODE_ORDER).' END)', $bindings)->toBase();

        return $query->orderByRaw('COALESCE(('.$rank->toSql().'), '.$notOnDuty.')', $rank->getBindings());
    }

    /**
     * @return Builder<PharmacyDutyShift>
     */
    private static function shiftsOf(?CarbonImmutable $now): Builder
    {
        return PharmacyDutyShift::query()
            ->onDate(PharmacyDutyShift::currentDutyDate($now))
            ->whereColumn('pharmacy_duty_shifts.facility_id', 'facilities.id');
    }

    private static function rank(string $mode): int
    {
        return self::MODE_ORDER[$mode] ?? count(self::MODE_ORDER);
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(PharmacyDutyShift $shift, bool $profiles): array
    {
        $facility = $shift->facility;
        $linked = $facility !== null && $facility->is_published && $facility->type === FacilityType::Pharmacy && ! $facility->trashed();

        return [
            'name' => $linked ? $facility->name : (string) TextCase::institution($shift->pharmacy_name),
            'municipality' => $shift->municipality,
            'address' => ($linked ? $facility->address : null) ?? $shift->address,
            'phone' => $shift->phone ?? ($linked ? $facility->phone : null),
            'mode' => $shift->mode,
            'hours_text' => $shift->hours_text,
            'slug' => $linked && $profiles ? $facility->slug : null,
            'latitude' => $linked ? $facility->latitude : null,
            'longitude' => $linked ? $facility->longitude : null,
        ];
    }
}
