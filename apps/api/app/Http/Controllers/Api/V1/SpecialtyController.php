<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpecialtyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;

class SpecialtyController extends Controller
{
    public function index(): JsonResponse
    {
        $specialties = Specialty::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            SpecialtyResource::collection($specialties)->resolve(),
        );
    }
}
