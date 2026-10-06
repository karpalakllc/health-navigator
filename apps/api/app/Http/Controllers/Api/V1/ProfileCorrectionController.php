<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProfileCorrectionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ProfileCorrection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * „Пријави грешка во профилот“ and the listed doctor's „Барање за приговор /
 * отстранување“ (docs/legal/research-memo.md §2.1). Anyone may send one, signed
 * in or not; the route limits how many per address or account. Only a
 * published profile can be the subject, so an unpublished draft answers 404
 * exactly as its public page does.
 *
 * Staff are emailed a summary of new requests (corrections:alert-staff) and
 * handle them in the admin panel (Directory → Corrections).
 */
class ProfileCorrectionController extends Controller
{
    public function storeForDoctor(string $slug, StoreProfileCorrectionRequest $request): JsonResponse
    {
        $doctor = Doctor::query()->published()->where('slug', $slug)->first();

        return $this->store($doctor, $request);
    }

    public function storeForFacility(string $slug, StoreProfileCorrectionRequest $request): JsonResponse
    {
        $facility = Facility::query()->published()->clinical()->where('slug', $slug)->first();

        return $this->store($facility, $request);
    }

    /**
     * The one answer every accepted request gets, the honeypot's included.
     */
    public static function received(ProfileCorrectionType $type): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'received',
            'message' => __('api.profile_correction.received_'.$type->value),
        ], 201);
    }

    private function store(?Model $subject, StoreProfileCorrectionRequest $request): JsonResponse
    {
        if ($subject === null) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        $type = $request->correctionType();

        if ($request->isBot()) {
            return self::received($type);
        }

        ProfileCorrection::query()->create([
            'type' => $type,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'field' => $request->correctionField(),
            'message' => $request->correctionMessage(),
            'contact' => $request->correctionContact(),
            'user_id' => $request->user()?->getKey(),
            'status' => ProfileCorrectionStatus::Open,
            'due_at' => now()->addDays($type->dueDays()),
        ]);

        return self::received($type);
    }
}
