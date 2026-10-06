<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin ForumTag
 */
class ForumTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'latin' => $this->latin,
            // Present only where the query counted them (tag list and page).
            'topics_count' => $this->when(
                $this->resource->hasAttribute('visible_topics_count'),
                fn (): int => (int) $this->resource->getAttribute('visible_topics_count'),
            ),
            'last_activity_at' => $this->when(
                $this->resource->hasAttribute('last_activity_at'),
                fn (): ?string => $this->resource->getAttribute('last_activity_at') === null
                    ? null
                    : Carbon::parse($this->resource->getAttribute('last_activity_at'))->toIso8601String(),
            ),
        ];
    }
}
