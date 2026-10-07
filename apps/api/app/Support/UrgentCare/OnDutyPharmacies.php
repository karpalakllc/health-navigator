<?php

namespace App\Support\UrgentCare;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\PharmacyDutyShift;
use App\Models\SiteSetting;
use App\Support\Import\Pharmacies\OnDutyNames;
use App\Support\Import\TextCase;
use Carbon\CarbonImmutable;

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
                ->sortBy(fn (array $item): string => self::MODE_ORDER[$item['mode']].'|'.mb_strtolower($item['name']))
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
        $date = PharmacyDutyShift::currentDutyDate($now);
        $shift = PharmacyDutyShift::query()->onDate($date)->where('facility_id', $pharmacy->getKey())->first();

        return $shift === null ? null : [
            'date' => $date->toDateString(),
            'mode' => $shift->mode,
            'hours_text' => $shift->hours_text,
        ];
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
