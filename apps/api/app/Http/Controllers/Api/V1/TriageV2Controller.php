<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\TriageSession;
use App\Services\Triage\V2\GuidanceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Symptom guidance v2 (docs/triage-flows.md). Sessions are anonymous: never
 * linked to an account even when a token is sent, continued only with the
 * secret issued at creation (X-Guidance-Token), whose hash alone is stored.
 */
class TriageV2Controller extends Controller
{
    public function __construct(
        private readonly GuidanceSessionService $guidance,
    ) {}

    public function catalog(): JsonResponse
    {
        $flows = $this->guidance->catalog();

        if ($flows === []) {
            return ApiResponse::errorCode('guidance.unavailable', 404);
        }

        return ApiResponse::success([
            'flows' => $flows,
            'max_symptoms' => (int) config('triage.max_symptoms', 3),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accepted_terms' => ['required', 'accepted'],
        ]);

        if ($this->guidance->catalog() === []) {
            return ApiResponse::errorCode('guidance.unavailable', 404);
        }

        [$session, $token] = $this->guidance->start((bool) $validated['accepted_terms']);

        return ApiResponse::success([
            'session_id' => $session->id,
            'session_token' => $token,
            'state' => $this->guidance->state($session),
        ], 201);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        return ApiResponse::success($this->guidance->state($this->find($id, $request)));
    }

    public function demographics(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'age_value' => ['required', 'numeric', 'min:0', 'max:1500'],
            'age_unit' => ['required', 'string', 'in:years,months,weeks'],
            'sex' => ['required', 'string', 'max:16'],
            'pregnancy' => ['nullable', 'string', 'max:16'],
            'conditions' => ['present', 'array', 'max:6'],
            'conditions.*' => ['string', 'max:40', 'distinct'],
        ]);

        return ApiResponse::success($this->guidance->saveDemographics($this->find($id, $request), $validated));
    }

    public function symptoms(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flows' => ['required', 'array', 'min:1', 'max:10'],
            'flows.*' => ['string', 'max:64', 'distinct'],
        ]);

        return ApiResponse::success($this->guidance->chooseSymptoms($this->find($id, $request), $validated['flows']));
    }

    public function screen(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'red_flags' => ['present', 'array', 'max:60'],
            'red_flags.*' => ['string', 'max:120', 'distinct'],
        ]);

        return ApiResponse::success($this->guidance->answerScreen($this->find($id, $request), $validated['red_flags']));
    }

    public function answer(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flow' => ['required', 'string', 'max:64'],
            'node' => ['required', 'string', 'max:64'],
            'values' => ['present', 'array', 'max:15'],
            'values.*' => ['string', 'max:64'],
        ]);

        return ApiResponse::success($this->guidance->answer(
            $this->find($id, $request),
            $validated['flow'],
            $validated['node'],
            $validated['values'],
        ));
    }

    public function emergency(string $id, Request $request): JsonResponse
    {
        return ApiResponse::success($this->guidance->emergencyShortcut($this->find($id, $request)));
    }

    public function noMatch(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body_area' => ['nullable', 'string', 'max:16'],
        ]);

        return ApiResponse::success($this->guidance->noMatch($this->find($id, $request), $validated['body_area'] ?? null));
    }

    private function find(string $id, Request $request): TriageSession
    {
        if (! Str::isUuid($id)) {
            abort(404);
        }

        $session = TriageSession::query()->find($id);

        // 404 for a v1 session, an unknown id or a wrong secret alike, so the
        // endpoint never confirms that a session exists.
        if ($session === null
            || $session->engine !== TriageSession::ENGINE_V2
            || ! $session->tokenMatches($request->header(TriageController::TOKEN_HEADER))) {
            abort(404);
        }

        return $session;
    }
}
