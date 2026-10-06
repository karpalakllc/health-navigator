<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A review as the home page teases it: the author's public display name (never
 * `users.name` or the email), a short excerpt, and the profile it is about.
 *
 * @mixin Review
 */
class HomeReviewResource extends JsonResource
{
    public const EXCERPT_LENGTH = 160;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewable = $this->reviewable;

        $target = match (true) {
            $reviewable instanceof Doctor => [
                'kind' => 'doctor',
                'slug' => $reviewable->slug,
                'name' => $reviewable->full_name,
            ],
            $reviewable instanceof Facility => [
                'kind' => $reviewable->isPharmacy() ? 'pharmacy' : 'facility',
                'slug' => $reviewable->slug,
                'name' => $reviewable->name,
            ],
            default => null,
        };

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'excerpt' => self::excerpt($this->body),
            'author_name' => $this->user->publicName(),
            'published_at' => $this->published_at?->toIso8601String(),
            'target' => $target,
        ];
    }

    /** Whitespace collapsed, then cut at a word boundary with an ellipsis. */
    public static function excerpt(?string $body): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', (string) $body));

        return Str::limit($flat, self::EXCERPT_LENGTH, '…', preserveWords: true);
    }
}
