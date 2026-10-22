<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPharmaciesRequest;
use App\Http\Requests\Api\V1\ListPharmacyProductsRequest;
use App\Http\Resources\Api\V1\PharmacyDetailResource;
use App\Http\Resources\Api\V1\PharmacyListResource;
use App\Http\Resources\Api\V1\PharmacyShelfProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Facility;
use App\Support\PharmacyCatalog;
use App\Support\ScriptInsensitiveSearch;
use App\Support\Search\FuzzyNameSearch;
use App\Support\UrgentCare\OnDutyPharmacies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PharmacyController extends Controller
{
    public function index(ListPharmaciesRequest $request, FuzzyNameSearch $fuzzy): JsonResponse
    {
        $validated = $request->validated();

        $query = Facility::query()
            ->published()
            ->pharmacy();

        $city = trim((string) ($validated['city'] ?? ''));
        $onDutyOnly = ! empty($validated['on_duty']);

        if ($onDutyOnly) {
            // „Само дежурни денес“: on ФЗОМ's schedule for the duty day.
            OnDutyPharmacies::whereOnDuty($query);
        }

        if ($city !== '') {
            $query->cityContains($city);
        }

        if (! empty($validated['verified'])) {
            $query->verified();
        }

        $q = $validated['q'] ?? null;
        $perPage = $validated['per_page'] ?? 15;
        // Every filter but the name: the base a fuzzy fallback starts from.
        $base = $query->clone();

        if (! empty($q)) {
            $query->searchName($q);
        }

        if ($city !== '' || $onDutyOnly) {
            // In a city, tonight's on-duty pharmacies lead (24 h before on
            // call), then the usual order. A fuzzy match keeps closeness first.
            OnDutyPharmacies::orderOnDutyFirst($query);
        }

        $paginator = $this->listed($query)->paginate($perPage)->withQueryString();
        $meta = [];

        // No exact match: names close to the query, under the same filters.
        if (! empty($q) && $paginator->total() === 0 && FuzzyNameSearch::eligible($q)) {
            $match = $fuzzy->facilities($base, $q);
            $meta = ['fuzzy' => $match !== null, 'suggestion' => $match?->suggestion];

            if ($match !== null) {
                $paginator = $this->listed(FuzzyNameSearch::applyToFacilities($base, $match))
                    ->paginate($perPage)
                    ->withQueryString();
            }
        }

        return ApiResponse::paginated(
            $paginator,
            PharmacyListResource::collection($paginator),
            $meta,
        );
    }

    /**
     * Featured first within the filters, then by name; the id breaks ties
     * between equal names so pages never overlap or skip rows. (A fuzzy
     * match orders by closeness first; these break its ties.)
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    private function listed(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->orderBy('facilities.id');
    }

    public function show(string $slug): JsonResponse
    {
        $pharmacy = $this->findPublishedPharmacy($slug);

        return ApiResponse::success(new PharmacyDetailResource($pharmacy));
    }

    public function products(string $slug, ListPharmacyProductsRequest $request): JsonResponse
    {
        $pharmacy = $this->findPublishedPharmacy($slug);
        $validated = $request->validated();

        $query = PharmacyCatalog::availableProductsRelation($pharmacy);

        if (! empty($validated['q'])) {
            // The relation's underlying builder: the helper takes an Eloquent Builder.
            ScriptInsensitiveSearch::whereColumnMatches($query->getQuery(), 'products.name', $validated['q']);
        }

        if (! empty($validated['category'])) {
            if ($query->getConnection()->getDriverName() === 'pgsql') {
                $query->where('products.category', 'ilike', $validated['category']);
            } else {
                $query->whereRaw('LOWER(products.category) = ?', [mb_strtolower($validated['category'])]);
            }
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            PharmacyShelfProductResource::collection($paginator),
        );
    }

    protected function findPublishedPharmacy(string $slug): Facility
    {
        return Facility::query()
            ->published()
            ->pharmacy()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
