<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StartTriageSessionRequest;
use App\Http\Requests\Api\V1\StoreTriageAnswersRequest;
use App\Http\Resources\Api\V1\TriageFlowResource;
use App\Http\Responses\ApiResponse;
use App\Models\TriageSession;
use App\Models\User;
use App\Services\Triage\TriageSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class TriageController extends Controller
{
    public function __construct(
        private readonly TriageSessionService $sessions,
    ) {}

    public function showFlow(): JsonResponse
    {
        try {
            $flow = $this->sessions->publishedFlow();
        } catch (ValidationException) {
            return ApiResponse::error('Symptom guidance is not available.', 404);
        }

        return ApiResponse::success(new TriageFlowResource($flow));
    }

    public function startSession(StartTriageSessionRequest $request): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $validated = $request->validated();

        $session = $this->sessions->startSession(
            $flow,
            (bool) $validated['accepted_terms'],
            $this->optionalUser($request),
        );

        return ApiResponse::success([
            'session_id' => $session->id,
        ], 201);
    }

    public function storeAnswers(string $id, StoreTriageAnswersRequest $request): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $session = $this->findOpenSession($id, $flow->id);

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
        $session = $this->findOpenSession($id, $flow->id);

        $session = $this->sessions->markEmergency($session, $flow);

        $outcome = $this->sessions->complete($session, $flow);

        return ApiResponse::success([
            'session_id' => $session->id,
            'emergency_stopped' => true,
            'outcome' => $outcome,
        ]);
    }

    public function complete(string $id): JsonResponse
    {
        $flow = $this->sessions->publishedFlow();
        $session = $this->findSession($id, $flow->id);

        $outcome = $this->sessions->complete($session, $flow);

        return ApiResponse::success([
            'session_id' => $session->fresh()->id,
            'outcome' => $outcome,
        ]);
    }

    private function findSession(string $id, int $flowId): TriageSession
    {
        $session = TriageSession::query()->find($id);

        if ($session === null || $session->triage_flow_id !== $flowId) {
            abort(404);
        }

        return $session;
    }

    private function findOpenSession(string $id, int $flowId): TriageSession
    {
        $session = $this->findSession($id, $flowId);

        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'session' => ['This guidance session is already complete.'],
            ]);
        }

        return $session;
    }

    private function optionalUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken?->tokenable;
    }
}
