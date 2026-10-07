<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProfileReportRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ProfileCorrection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * „Пријави профил“ (W7-C): anyone, signed in or not, can tell staff that a
 * doctor, facility or pharmacy profile is fake, about the wrong person, out
 * of date or inappropriate. The report joins the corrections queue (type
 * report); the profile stays as it is until staff decide.
 *
 * Repeats are absorbed, not refused, so the answer is always the same and a
 * retry cannot fill the queue:
 * - a member has at most one open report per profile;
 * - a guest one per profile per day per address. The address is never
 *   stored: a keyed hash of it and the profile marks the day in the cache,
 *   and stays on the open report only so independent reporters can be
 *   counted (erased when staff close it).
 */
class ProfileReportController extends Controller
{
    private const GUEST_WINDOW_SECONDS = 86_400;

    public function storeForDoctor(string $slug, StoreProfileReportRequest $request): JsonResponse
    {
        return $this->store(Doctor::query()->published()->where('slug', $slug)->first(), $request);
    }

    public function storeForFacility(string $slug, StoreProfileReportRequest $request): JsonResponse
    {
        return $this->store(Facility::query()->published()->clinical()->where('slug', $slug)->first(), $request);
    }

    public function storeForPharmacy(string $slug, StoreProfileReportRequest $request): JsonResponse
    {
        return $this->store(Facility::query()->published()->pharmacy()->where('slug', $slug)->first(), $request);
    }

    /**
     * The one answer every accepted report gets, repeats and the honeypot's
     * included.
     */
    public static function received(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'received',
            'message' => __('api.profile_report.received'),
        ], 201);
    }

    private function store(?Model $subject, StoreProfileReportRequest $request): JsonResponse
    {
        if ($subject === null) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        if ($request->isBot()) {
            return self::received();
        }

        $user = $request->user();
        $reporterHash = null;

        if ($user !== null) {
            $alreadyOpen = ProfileCorrection::query()
                ->open()
                ->where('type', ProfileCorrectionType::Report->value)
                ->where('subject_type', $subject::class)
                ->where('subject_id', $subject->getKey())
                ->where('user_id', $user->getKey())
                ->exists();

            if ($alreadyOpen) {
                return self::received();
            }
        } else {
            $reporterHash = self::guestHash((string) $request->ip(), $subject);

            if (! Cache::add('profile-report:guest:'.$reporterHash, true, self::GUEST_WINDOW_SECONDS)) {
                return self::received();
            }
        }

        ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Report,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'report_reason' => $request->reason(),
            'message' => $request->note(),
            'user_id' => $user?->getKey(),
            'reporter_hash' => $reporterHash,
            'status' => ProfileCorrectionStatus::Open,
            'due_at' => now()->addDays(ProfileCorrectionType::Report->dueDays()),
        ]);

        return self::received();
    }

    /**
     * HMAC of the guest's network and the profile under the app key: the
     * same guest on the same profile gives the same value, but it cannot be
     * reversed or linked across profiles without the key. An IPv6 visitor
     * usually holds a whole /64, so that is the guest; IPv4 is per address.
     */
    public static function guestHash(string $ip, Model $subject): string
    {
        return hash_hmac(
            'sha256',
            'profile-report|'.self::guestNetwork($ip).'|'.$subject::class.'|'.$subject->getKey(),
            (string) config('app.key'),
        );
    }

    /**
     * IPv4: the address (/32). IPv6: its /64 prefix, normalised.
     */
    public static function guestNetwork(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        // ::ffff:a.b.c.d is an IPv4 visitor.
        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
