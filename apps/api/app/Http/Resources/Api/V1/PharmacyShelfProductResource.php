<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Support\FormatsDates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class PharmacyShelfProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->pivot->price,
            'currency' => $this->pivot->currency,
            'price_updated_at' => FormatsDates::toIso8601($this->pivot->price_updated_at),
        ];
    }
}
