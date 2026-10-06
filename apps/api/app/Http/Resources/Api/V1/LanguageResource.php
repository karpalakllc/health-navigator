<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Language
 */
class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'doctors_count' => (int) ($this->doctors_count ?? 0),
        ];
    }
}
