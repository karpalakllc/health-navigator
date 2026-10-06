<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StartTriageSessionRequest;
use App\Http\Requests\Api\V1\StoreTriageAnswersRequest;
use App\Http\Resources\Api\V1\TriageFlowResource;
use App\Http\Responses\ApiResponse;
use App\Models\TriageSession;
use App\Services\Triage\TriageSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TriageController extends Controller
{
    /** Carries the secret issued with a session; required for every later call on it. */
    public const TOKEN_HEADER = 'X-Guidance-Token';

    public function __construct(
        private readonly TriageSessionService $sessions,
    ) {}

    public function showFlow(): JsonResponse
    {
        try {
            $flow = $this->sessions->publishedFlow();
        } catch (ValidationException) {
            return ApiResponse::errorCode('guidance.unavailable', 404);
        }

        return ApiResponse::success(new TriageFlowResource($flow));
    }

    public function startSession(StartTriageSessionRequest $request): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $validated = $request->validated();

        // Deliberately not linked to the caller, even when signed in — see
        // TriageSessionService::startSession().
        [$session, $token] = $this->sessions->startSession(
            $flow,
            (bool) $validated['accepted_terms'],
        );

        return ApiResponse::success([
            'session_id' => $session->id,
            'session_token' => $token,
        ], 201);
    }

    public function storeAnswers(string $id, StoreTriageAnswersRequest $request): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $session = $this->findOpenSession($id, $flow->id, $request);

        $session = $this->sessions->storeAnswers(
            $session,
            $flow,
            $request->validated('answers'),
        );

        return ApiResponse::success([
            'session_id' => $session->id,
            'emergency_stopped' => $session->emergency_stopped,
        ]);
    }

    public function emergency(string $id, Request $request): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $session = $this->findOpenSession($id, $flow->id, $request);

        $session = $this->sessions->markEmergency($session, $flow);

        $outcome = $this->sessions->complete($session, $flow);

        return ApiResponse::success([
            'session_id' => $session->id,
            'emergency_stopped' => true,
            'outcome' => $outcome,
        ]);
    }

    public function complete(string $id, Request $request): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $session = $this->findSession($id, $flow->id, $request);

        $outcome = $this->sessions->complete($session, $flow);

        return ApiResponse::success([
            'session_id' => $session->fresh()->id,
            'outcome' => $outcome,
        ]);
    }

    private function findSession(string $id, int $flowId, Request $request): TriageSession
    {
        // Session ids are UUIDs; anything else is a plain 404 rather than a
        // driver error (PostgreSQL rejects a malformed uuid literal → 500).
        if (! Str::isUuid($id)) {
            abort(404);
        }

        $session = TriageSession::query()->find($id);

        if ($session === null || $session->triage_flow_id !== $flowId) {
            abort(404);
        }

        // Sessions belong to no account, so the id alone must not be enough to
        // read or steer one: the caller presents the secret it was issued at
        // creation. 404 rather than 403 so the endpoint does not confirm that a
        // given session id exists.
        if (! $session->tokenMatches($request->header(self::TOKEN_HEADER))) {
            abort(404);
        }

        return $session;
    }

    private function findOpenSession(string $id, int $flowId, Request $request): TriageSession
    {
        $session = $this->findSession($id, $flowId, $request);

        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'session' => [__('api.guidance.session_complete')],
            ]);
        }

        return $session;
    }
}
