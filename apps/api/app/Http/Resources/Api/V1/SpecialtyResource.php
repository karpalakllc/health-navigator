<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Specialty
 */
class SpecialtyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'doctors_count' => (int) ($this->doctors_count ?? 0),
        ];
    }
}
