<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Support\MacedonianSearchVariants;
use App\Support\TaxonomyCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * GET /locations/cities: every city that has at least one published profile
 * (doctors, clinical facilities, and pharmacies while that module is on),
 * with counts. The web's city picker reads it to tell which places of the
 * static territorial list actually hold listings: a municipality without
 * any is searched by its parent city instead.
 *
 * Spellings that differ only in script, case or surrounding spaces are merged
 * under the commonest one, as on /home/highlights. Cached in the
 * home-highlights group, which doctor and facility saves already bust.
 */
class LocationController extends Controller
{
    public const CACHE_TTL_SECONDS = 300;

    public function cities(): JsonResponse
    {
        $pharmaciesOn = (bool) SiteSetting::current()->public_pharmacies;

        $payload = TaxonomyCache::remember(
            TaxonomyCache::HOME_HIGHLIGHTS,
            'locations-cities:pharmacies-'.($pharmaciesOn ? 'on' : 'off'),
            fn () => $this->cityRows($pharmaciesOn),
            self::CACHE_TTL_SECONDS,
        );

        // A list, which ApiResponse takes as a collection (an object).
        return ApiResponse::success(collect($payload));
    }

    /**
     * @return list<array{name: string, doctors_count: int, facilities_count: int}>
     */
    private function cityRows(bool $pharmaciesOn): array
    {
        $facilities = Facility::query()->published();

        if (! $pharmaciesOn) {
            $facilities->clinical();
        }

        /** @var array<string, array{name: string, doctors_count: int, facilities_count: int, total: int}> $merged */
        $merged = [];

        foreach ([
            'doctors_count' => Doctor::query()->published(),
            'facilities_count' => $facilities,
        ] as $field => $query) {
            foreach ($this->countsByCity($query) as $name => $count) {
                $key = mb_strtolower(MacedonianSearchVariants::latinToCyrillic($name));
                // Biggest spelling first within each source: the first one seen
                // names the merged row.
                $merged[$key] ??= ['name' => $name, 'doctors_count' => 0, 'facilities_count' => 0, 'total' => 0];
                $merged[$key][$field] += $count;
                $merged[$key]['total'] += $count;
            }
        }

        $rows = array_values($merged);

        usort($rows, fn (array $a, array $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);

        return array_map(
            fn (array $row) => [
                'name' => $row['name'],
                'doctors_count' => $row['doctors_count'],
                'facilities_count' => $row['facilities_count'],
            ],
            $rows,
        );
    }

    /**
     * @param  Builder<Doctor>|Builder<Facility>  $query
     * @return array<string, int> trimmed city name => count, biggest first
     */
    private function countsByCity(Builder $query): array
    {
        $rows = $query
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->selectRaw('city, COUNT(*) AS aggregate')
            ->groupBy('city')
            ->orderByDesc('aggregate')
            ->orderBy('city')
            ->toBase()
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->city);

            if ($name !== '') {
                $counts[$name] = ($counts[$name] ?? 0) + (int) $row->aggregate;
            }
        }

        return $counts;
    }
}
