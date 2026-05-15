<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Phase 1 JSON conventions for versioned API routes.
 * Success: { "data": ... }. Error: { "message": "...", "errors"?: { field: string[] } }.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|object  $data
     */
    public static function success(array|object $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        AnonymousResourceCollection $data,
    ): JsonResponse {
        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
