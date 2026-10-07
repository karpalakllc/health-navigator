<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UrgentCareFacilityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Facility;
use App\Support\MacedonianSearchVariants;
use App\Support\TaxonomyCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * „Каде веднаш“ (docs/urgent-care.md): published clinical facilities with an
 * urgent-care service — emergency department (ed), emergency medical service
 * (ems), on-duty clinic (clinic), dental emergency (dental).
 *
 * On-duty pharmacies: no source holds them yet, so `meta.on_duty_pharmacies`
 * says so and the site shows a placeholder.
 */
class UrgentCareController extends Controller
{
    public const CACHE_TTL_SECONDS = 300;

    /** A city has a few dozen at most; the whole country stays under this. */
    public const MAX_RESULTS = 300;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(array_keys(Facility::URGENT_CARE_SERVICES))],
        ]);

        $city = isset($validated['city']) ? trim((string) $validated['city']) : '';
        $type = $validated['type'] ?? null;

        $query = Facility::query()
            ->published()
            ->clinical()
            ->urgentCare($type);

        if ($city !== '') {
            $query->cityContains($city);
        }

        // Emergency departments first, then emergency medical services, on-duty
        // clinics and dental services; round-the-clock first within each.
        foreach (Facility::URGENT_CARE_SERVICES as $column) {
            $query->orderByDesc($column);
        }

        $facilities = $query
            ->orderByDesc('is_open_24h')
            ->orderBy('name')
            ->orderBy('facilities.id')
            ->limit(self::MAX_RESULTS)
            ->get();

        return response()->json([
            'data' => UrgentCareFacilityResource::collection($facilities),
            'meta' => [
                'city' => $city !== '' ? $city : null,
                'type' => $type,
                'total' => $facilities->count(),
                'on_duty_pharmacies' => ['available' => false],
            ],
        ]);
    }

    /**
     * Cities with at least one published urgent-care place, with a count per
     * service. Skopje's municipalities („Скопје - Карпош“) count as Скопје.
     */
    public function cities(): JsonResponse
    {
        $rows = TaxonomyCache::remember(
            TaxonomyCache::HOME_HIGHLIGHTS,
            'urgent-care-cities',
            fn () => $this->cityRows(),
            self::CACHE_TTL_SECONDS,
        );

        return ApiResponse::success(collect($rows));
    }

    /**
     * @return list<array{name: string, total: int, ed: int, ems: int, clinic: int, dental: int}>
     */
    private function cityRows(): array
    {
        $columns = Facility::URGENT_CARE_SERVICES;
        $facilities = Facility::query()
            ->published()
            ->clinical()
            ->urgentCare()
            ->whereNotNull('city')
            ->get(['city', ...array_values($columns)]);

        /** @var array<string, array{name: string, total: int, ed: int, ems: int, clinic: int, dental: int}> $merged */
        $merged = [];

        foreach ($facilities as $facility) {
            $name = self::baseCity((string) $facility->city);

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower(MacedonianSearchVariants::latinToCyrillic($name));
            $merged[$key] ??= ['name' => $name, 'total' => 0, 'ed' => 0, 'ems' => 0, 'clinic' => 0, 'dental' => 0];
            $merged[$key]['total']++;

            foreach ($columns as $service => $column) {
                if ((bool) $facility->getAttribute($column)) {
                    $merged[$key][$service]++;
                }
            }
        }

        $rows = array_values($merged);
        usort($rows, fn (array $a, array $b): int => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);

        return $rows;
    }

    /**
     * „Скопје - Карпош“ → „Скопје“; anything else trimmed.
     */
    public static function baseCity(string $city): string
    {
        $city = trim($city);
        $parts = preg_split('/\s+[-–—]\s+/u', $city, 2);

        return trim($parts[0] ?? $city);
    }
}
