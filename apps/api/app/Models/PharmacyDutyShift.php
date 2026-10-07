<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pharmacy on duty on one day, from ФЗОМ's monthly schedule
 * (import:on-duty-pharmacies, docs/urgent-care.md § On-duty pharmacies).
 *
 * @property string $month
 * @property CarbonImmutable $duty_date
 * @property string $town
 * @property string $town_key
 * @property string|null $municipality
 * @property string $pharmacy_name
 * @property string $name_key
 * @property int|null $facility_id
 * @property string|null $phone
 * @property string $mode
 * @property string|null $hours_text
 * @property string|null $address
 */
class PharmacyDutyShift extends Model
{
    public const MODE_ALL_DAY = 'all_day';

    public const MODE_HOURS = 'hours';

    public const MODE_ON_CALL = 'on_call';

    public const MODE_UNKNOWN = 'unknown';

    /** Night duties end in the morning: before this hour „tonight“ is still yesterday's list. */
    public const NIGHT_ENDS_AT_HOUR = 7;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'duty_date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * The schedule day that is „on duty now“ in Skopje: before 07:00 the
     * previous day's night duty is still running.
     */
    public static function currentDutyDate(?CarbonImmutable $now = null): CarbonImmutable
    {
        $local = ($now ?? CarbonImmutable::now())->setTimezone('Europe/Skopje');

        return ($local->hour < self::NIGHT_ENDS_AT_HOUR ? $local->subDay() : $local)->startOfDay();
    }

    /**
     * @param  Builder<PharmacyDutyShift>  $query
     * @return Builder<PharmacyDutyShift>
     */
    public function scopeOnDate(Builder $query, CarbonImmutable $date): Builder
    {
        return $query->whereDate('duty_date', $date->toDateString());
    }
}
