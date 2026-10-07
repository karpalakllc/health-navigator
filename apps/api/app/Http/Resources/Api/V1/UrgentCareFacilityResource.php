<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One place on „Каде веднаш“ (GET /urgent-care). The evidence the flags came
 * from stays internal; hours are only sent when staff entered them (or the
 * institution states 24/7), so the site never guesses „open now“.
 *
 * @mixin Facility
 */
class UrgentCareFacilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $services = [];

        $edStatus = (bool) $this->has_emergency_services
            ? Facility::ED_CONFIRMED
            : ($this->emergency_department_status === Facility::ED_UNCONFIRMED_LIKELY ? Facility::ED_UNCONFIRMED_LIKELY : null);

        foreach (Facility::URGENT_CARE_SERVICES as $service => $column) {
            if ((bool) $this->getAttribute($column) || ($service === 'ed' && $edStatus !== null)) {
                $services[] = $service;
            }
        }

        $hours = is_array($this->emergency_hours) && $this->emergency_hours !== [] ? $this->emergency_hours : null;

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type->value,
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'emergency_phone' => $this->emergency_phone,
            // ed | ems | clinic | dental, in that order.
            'services' => $services,
            // confirmed | unconfirmed_likely („итно одделение (непотврдено)“) | null
            'ed_status' => $edStatus,
            'is_open_24h' => (bool) $this->is_open_24h,
            // Same format as office_hours; null = not confirmed.
            'emergency_hours' => $hours,
            'hours_confirmed' => (bool) $this->is_open_24h || $hours !== null,
            'note' => $this->urgent_care_note,
            'checked_at' => $this->urgent_care_checked_at?->toDateString(),
            'verification' => $this->publicVerification(),
        ];
    }
}
