<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumPost
 */
class ForumPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author_name' => $this->user->name,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
