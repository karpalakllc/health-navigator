<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Facility;
use App\Models\Product;
use App\Support\FormatsDates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @param  Collection<int, Facility>  $offers
     */
    public function __construct(
        Product $resource,
        protected $offers,
        protected int $offersTotal,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'offers' => $this->offers->map(fn ($pharmacy) => [
                'pharmacy' => [
                    'slug' => $pharmacy->slug,
                    'name' => $pharmacy->name,
                    'city' => $pharmacy->city,
                ],
                'price' => (float) $pharmacy->pivot->price,
                'currency' => $pharmacy->pivot->currency,
                'price_updated_at' => FormatsDates::toIso8601($pharmacy->pivot->price_updated_at),
            ])->values(),
            'offers_total' => $this->offersTotal,
            'offers_truncated' => $this->offersTotal > Product::MAX_EMBEDDED_OFFERS,
        ];
    }
}
