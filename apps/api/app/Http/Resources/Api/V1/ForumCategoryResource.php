<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumCategory
 */
class ForumCategoryResource extends JsonResource
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
            'topics_count' => $this->whenCounted('topics'),
        ];
    }
}
