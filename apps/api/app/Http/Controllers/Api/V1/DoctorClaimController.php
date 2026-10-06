<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DoctorClaimRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDoctorClaimRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\DoctorClaimRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * „Ова е мој профил“: a signed-in member asks to manage a published doctor
 * profile. Staff verify the person outside the platform and then assign the
 * account in the admin panel (Directory → Profile claims). Nothing about the
 * profile changes here.
 */
class DoctorClaimController extends Controller
{
    /** Open requests one account may have at a time, across profiles. */
    private const MAX_PENDING_PER_ACCOUNT = 3;

    public function store(string $slug, StoreDoctorClaimRequest $request): JsonResponse
    {
        $doctor = Doctor::query()->published()->where('slug', $slug)->first();

        if ($doctor === null) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        /** @var User $user */
        $user = $request->user();

        if ($doctor->isOwnedBy($user)) {
            return ApiResponse::errorCode('doctor_account.claim_already_yours', 409);
        }

        if ($doctor->owner_user_id !== null) {
            return ApiResponse::errorCode('doctor_account.claim_taken', 409);
        }

        if (Doctor::managedBy($user) !== null) {
            return ApiResponse::errorCode('doctor_account.claim_already_manager', 409);
        }

        $existing = DoctorClaimRequest::query()
            ->pending()
            ->where('user_id', $user->getKey())
            ->where('doctor_id', $doctor->getKey())
            ->exists();

        // Asking again changes nothing: staff already have the request.
        if (! $existing) {
            $open = DoctorClaimRequest::query()->pending()->where('user_id', $user->getKey())->count();

            if ($open >= self::MAX_PENDING_PER_ACCOUNT) {
                return ApiResponse::errorCode('doctor_account.claim_limit', 429);
            }

            DoctorClaimRequest::query()->create([
                'doctor_id' => $doctor->getKey(),
                'user_id' => $user->getKey(),
                'message' => $request->claimMessage(),
                'contact' => $request->claimContact(),
                'status' => DoctorClaimRequestStatus::Pending,
            ]);
        }

        return ApiResponse::success([
            'status' => DoctorClaimRequestStatus::Pending->value,
            'message' => __('api.doctor_account.claim_received'),
        ], $existing ? 200 : 201);
    }
}
