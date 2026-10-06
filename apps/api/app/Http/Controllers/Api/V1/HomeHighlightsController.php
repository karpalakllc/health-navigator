<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FacilityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HomeReviewResource;
use App\Http\Resources\Api\V1\HomeSpecialtyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Support\TaxonomyCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * GET /home/highlights: what the home page shows besides its directory tiles —
 * the specialties and cities with the most published doctors, and the latest
 * approved reviews of published profiles. One anonymous, identical-for-everyone
 * payload, so it is cached server side (busted by Doctor, Specialty, Facility
 * and Review saves) and marked public for the web tier.
 */
class HomeHighlightsController extends Controller
{
    public const SPECIALTIES_LIMIT = 8;

    public const CITIES_LIMIT = 12;

    public const REVIEWS_LIMIT = 4;

    /**
     * Short on purpose: model events bust the cache, but a member changing
     * their display name saves no review, so the old name lives at most this long.
     */
    public const CACHE_TTL_SECONDS = 300;

    public function __invoke(): JsonResponse
    {
        // The pharmacies switch changes which reviews are visible; keying on
        // it means a toggle needs no flush.
        $pharmaciesOn = (bool) SiteSetting::current()->public_pharmacies;

        $payload = TaxonomyCache::remember(
            TaxonomyCache::HOME_HIGHLIGHTS,
            'index:pharmacies-'.($pharmaciesOn ? 'on' : 'off'),
            fn () => [
                'specialties' => HomeSpecialtyResource::collection($this->specialties())->resolve(),
                'cities' => $this->cities(),
                'recent_reviews' => HomeReviewResource::collection($this->recentReviews($pharmaciesOn))->resolve(),
            ],
            self::CACHE_TTL_SECONDS,
        );

        return ApiResponse::success($payload);
    }

    /**
     * Published specialties by published-doctor count; empty ones are left out.
     *
     * @return Collection<int, Specialty>
     */
    private function specialties(): Collection
    {
        $publishedDoctors = fn ($query) => $query->published();

        return Specialty::query()
            ->published()
            ->whereHas('doctors', $publishedDoctors)
            ->withCount(['doctors as doctors_count' => $publishedDoctors])
            ->orderByDesc('doctors_count')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(self::SPECIALTIES_LIMIT)
            ->get();
    }

    /**
     * Published doctors per city. Doctors only: each chip links to the doctor
     * directory's `city` filter, so its count describes what the visitor lands
     * on. Spellings that differ only in case or surrounding spaces are merged
     * under the commonest one.
     *
     * @return list<array{name: string, doctors_count: int}>
     */
    private function cities(): array
    {
        $rows = Doctor::query()
            ->published()
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->selectRaw('city, COUNT(*) AS aggregate')
            ->groupBy('city')
            ->orderByDesc('aggregate')
            ->orderBy('city')
            ->toBase()
            ->get();

        $merged = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->city);

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            // Rows arrive biggest first, so the first spelling seen is the commonest.
            $merged[$key] ??= ['name' => $name, 'doctors_count' => 0];
            $merged[$key]['doctors_count'] += (int) $row->aggregate;
        }

        $cities = array_values($merged);

        usort($cities, fn (array $a, array $b) => [$b['doctors_count'], $a['name']] <=> [$a['doctors_count'], $b['name']]);

        return array_slice($cities, 0, self::CITIES_LIMIT);
    }

    /**
     * The latest approved reviews that have something to read, of profiles a
     * visitor can open: published (and not deleted) doctors, clinical
     * facilities, and pharmacies while that module is on.
     *
     * @return Collection<int, Review>
     */
    private function recentReviews(bool $pharmaciesOn): Collection
    {
        $facilityTypes = FacilityType::clinicalValues();

        if ($pharmaciesOn) {
            $facilityTypes[] = FacilityType::Pharmacy->value;
        }

        return Review::query()
            ->approved()
            ->whereNotNull('published_at')
            ->whereNotNull('body')
            ->where('body', '<>', '')
            ->whereHasMorph(
                'reviewable',
                [Doctor::class, Facility::class],
                function (Builder $query, string $type) use ($facilityTypes): void {
                    $query->where('is_published', true);

                    if ($type === Facility::class) {
                        $query->whereIn('type', $facilityTypes);
                    }
                },
            )
            ->with([
                // `name` only feeds publicName()'s fallback; it is never sent.
                'user:id,name,display_name',
                'reviewable',
            ])
            ->latest('published_at')
            ->orderByDesc('id')
            ->limit(self::REVIEWS_LIMIT)
            ->get();
    }
}
