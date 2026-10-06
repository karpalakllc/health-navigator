<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DepartmentListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use App\Support\TaxonomyCache;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $payload = TaxonomyCache::remember(TaxonomyCache::DEPARTMENTS, 'index', fn () => DepartmentListResource::collection(
            Department::query()
                ->published()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        )->resolve());

        return ApiResponse::success($payload);
    }
}
