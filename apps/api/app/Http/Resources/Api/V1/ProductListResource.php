<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductListResource extends JsonResource
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
            'from_price' => $this->from_price !== null ? (float) $this->from_price : null,
            'offer_count' => (int) ($this->offer_count ?? 0),
        ];
    }
}
