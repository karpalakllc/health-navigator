<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Facility;
use App\Support\Media\MediaUrl;
use App\Support\ReviewSummary;
use App\Support\UrgentCare\OnDutyPharmacies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Facility
 */
class PharmacyListResource extends JsonResource
{
    /**
     * „Дежурна денес“ preloaded for the whole page by newCollection(); unset
     * on a lone resource, which then looks its own shift up.
     *
     * @var array{date: string, mode: string, hours_text: string|null}|null
     */
    private ?array $onDutyToday = null;

    private bool $onDutyLoaded = false;

    /**
     * Every collection of this resource — GET /pharmacies and the unified
     * search vertical, SQL or Meilisearch — loads today's shifts for its
     * page in one query (OnDutyPharmacies::forPharmacies), never per card.
     *
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): AnonymousResourceCollection
    {
        $collection = parent::newCollection($resource);

        $ids = $collection->collection
            ->map(fn (self $item): int => (int) $item->resource->getKey())
            ->all();
        $duties = OnDutyPharmacies::forPharmacies($ids);

        $collection->collection->each(function (self $item) use ($duties): void {
            $item->onDutyToday = $duties[(int) $item->resource->getKey()] ?? null;
            $item->onDutyLoaded = true;
        });

        return $collection;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'city' => $this->city,
            'avatar_url' => MediaUrl::resolve($this->avatar_url),
            'cover_url' => MediaUrl::resolve($this->cover_path),
            // Columns of the row already loaded: the card's „Јави се“ and
            // open-now line cost no extra query.
            'phone' => $this->phone,
            'office_hours' => $this->office_hours ?? [],
            'is_featured' => (bool) $this->is_featured,
            // „Верификуван“ / „Неверификуван“: status and public basis label
            // only — the evidence behind it stays internal.
            'verification' => $this->publicVerification(),
            'review_summary' => ReviewSummary::for($this->resource),
            // From ФЗОМ's schedule, for the duty day (OnDutyPharmacies):
            // same shape as the profile's; null when not on duty.
            'on_duty_today' => $this->onDutyLoaded
                ? $this->onDutyToday
                : OnDutyPharmacies::forPharmacy($this->resource),
        ];
    }
}
