<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DoctorChangeRequestStatus;
use App\Enums\ReviewResponseSource;
use App\Enums\ReviewResponseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDoctorChangeRequestRequest;
use App\Http\Requests\Api\V1\UpdateDoctorProfileRequest;
use App\Http\Resources\Api\V1\DoctorChangeRequestResource;
use App\Http\Resources\Api\V1\DoctorDashboardReviewResource;
use App\Http\Resources\Api\V1\ManagedDoctorResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClinicalInterest;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\Facility;
use App\Models\Language;
use App\Models\Procedure;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use App\Support\DoctorAccount\DoctorProfileFields;
use App\Support\Media\ImageOptimizer;
use App\Support\Media\InvalidImageException;
use App\Support\OfficeHours;
use App\Support\ReviewSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * „Мој профил“ (/me/doctor): the doctor profile staff linked to the signed-in
 * account. Practice details save at once; identity, qualifications,
 * specialties and workplaces go to staff as a change request; one reply per
 * published review, pre-moderated by default.
 *
 * Every endpoint resolves the profile from the account, never from the URL,
 * so another doctor's profile cannot be addressed at all; a review that is
 * not on the caller's profile is a 404 (ReviewPolicy::replyAsDoctor).
 */
class DoctorDashboardController extends Controller
{
    /** How many decided change requests the dashboard lists. */
    private const RECENT_DECISIONS = 5;

    public function show(Request $request): JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        return ApiResponse::success($this->dashboard($doctor, $request));
    }

    public function update(UpdateDoctorProfileRequest $request): JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        DoctorProfileFields::applyImmediate($doctor, DoctorProfileFields::normaliseImmediate($request->validated()));

        return ApiResponse::success($this->dashboard($doctor->fresh() ?? $doctor, $request));
    }

    public function updateAvatar(Request $request, ImageOptimizer $optimizer): JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        try {
            // The same pipeline and folder as the admin panel's doctor photo.
            $path = $optimizer->store($validated['avatar'], 'doctors', 1024, 1024);
        } catch (InvalidImageException) {
            throw ValidationException::withMessages([
                'avatar' => [__('api.avatar.invalid')],
            ]);
        }

        // The old file goes once the row points at the new one
        // (DeletesReplacedMedia); a failed save removes the new file instead.
        try {
            DB::transaction(function () use ($doctor, $path): void {
                Doctor::withTrashed()->whereKey($doctor->getKey())->lockForUpdate()->first();
                $doctor->forceFill(['avatar_url' => $path])->save();
            });
        } catch (Throwable $exception) {
            $optimizer->delete($path);

            throw $exception;
        }

        return ApiResponse::success($this->dashboard($doctor->fresh() ?? $doctor, $request));
    }

    public function storeChangeRequest(StoreDoctorChangeRequestRequest $request): JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $diff = DoctorProfileFields::sensitiveDiff($doctor, $request->validated());

        if ($diff === []) {
            return ApiResponse::errorCode('doctor_account.no_changes', 422);
        }

        $created = DB::transaction(function () use ($doctor, $request, $diff): ?DoctorChangeRequest {
            Doctor::withTrashed()->whereKey($doctor->getKey())->lockForUpdate()->first();

            // One request at a time: staff decide on a complete picture, and a
            // doctor cannot stack requests to wear a reviewer down.
            if ($doctor->changeRequests()->pending()->exists()) {
                return null;
            }

            /** @var User $user */
            $user = $request->user();

            return $doctor->changeRequests()->create([
                'user_id' => $user->getKey(),
                'changes' => $diff,
                'message' => filled($request->validated('message'))
                    ? trim(strip_tags((string) $request->validated('message')))
                    : null,
                'status' => DoctorChangeRequestStatus::Pending,
            ]);
        });

        if ($created === null) {
            return ApiResponse::errorCode('doctor_account.change_request_pending', 409);
        }

        return ApiResponse::success([
            'change_request' => (new DoctorChangeRequestResource($created->fresh()))->resolve($request),
            'message' => __('api.doctor_account.change_request_received'),
        ], 201);
    }

    public function withdrawChangeRequest(Request $request, int $changeRequest): JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $withdrawn = DB::transaction(function () use ($doctor, $changeRequest): bool {
            /** @var DoctorChangeRequest|null $pending */
            $pending = $doctor->changeRequests()->whereKey($changeRequest)->lockForUpdate()->first();

            if ($pending === null || ! $pending->isPending()) {
                return false;
            }

            $pending->forceFill(['status' => DoctorChangeRequestStatus::Withdrawn])->save();

            return true;
        });

        if (! $withdrawn) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        return ApiResponse::success($this->dashboard($doctor, $request));
    }

    public function reviews(Request $request): JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'filter' => ['nullable', 'string', 'in:all,unanswered'],
        ]);

        $query = $doctor->reviews()->approved()->with('user');

        if (($validated['filter'] ?? 'all') === 'unanswered') {
            $query->whereNull('response_body');
        }

        $paginator = $query->latest('published_at')->orderByDesc('id')->paginate(10)->withQueryString();

        return ApiResponse::paginated($paginator, DoctorDashboardReviewResource::collection($paginator));
    }

    public function upsertReply(Request $request, int $review): JsonResponse
    {
        $target = $this->ownReview($request, $review);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:'.Review::RESPONSE_MAX_LENGTH],
        ]);

        $body = Review::plainResponse((string) $validated['body']);

        if (mb_strlen($body) < 2) {
            throw ValidationException::withMessages([
                'body' => [__('validation.min.string', ['attribute' => 'body', 'min' => 2])],
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $requiresModeration = (bool) (SiteSetting::current()->doctor_replies_require_moderation ?? true);

        $saved = DB::transaction(function () use ($target, $user, $body, $requiresModeration): bool {
            /** @var Review $locked */
            $locked = Review::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            // A response staff entered on the profile's behalf is theirs to
            // change; the doctor can ask staff, or report the review.
            if ($locked->hasResponse() && ! $locked->hasDoctorReply()) {
                return false;
            }

            $locked->replyAsDoctor($user, $body, $requiresModeration);

            return true;
        });

        if (! $saved) {
            return ApiResponse::errorCode('doctor_account.reply_staff_exists', 409);
        }

        $fresh = Review::query()->with('user')->findOrFail($target->getKey());

        return ApiResponse::success([
            'review' => (new DoctorDashboardReviewResource($fresh))->resolve($request),
            'message' => $fresh->response_status === ReviewResponseStatus::Pending
                ? __('api.doctor_account.reply_pending')
                : __('api.doctor_account.reply_published'),
        ]);
    }

    public function destroyReply(Request $request, int $review): JsonResponse
    {
        $target = $this->ownReview($request, $review);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        if (! $target->hasDoctorReply()) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        $target->removeResponse();

        return ApiResponse::success([
            'review' => (new DoctorDashboardReviewResource($target->fresh(['user'])))->resolve($request),
        ]);
    }

    /**
     * The profile the account manages, or the response to send instead.
     */
    private function managedDoctor(Request $request): Doctor|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $doctor = Doctor::managedBy($user);

        if ($doctor === null || $doctor->trashed() || ! $user->can('manageOwnProfile', $doctor)) {
            return ApiResponse::errorCode('doctor_account.not_linked', 404);
        }

        return $doctor;
    }

    private function ownReview(Request $request, int $reviewId): Review|JsonResponse
    {
        $doctor = $this->managedDoctor($request);

        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        /** @var Review|null $review */
        $review = $doctor->reviews()->whereKey($reviewId)->first();

        if ($review === null) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        $review->setRelation('reviewable', $doctor);

        if (! $request->user()?->can('replyAsDoctor', $review)) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(Doctor $doctor, Request $request): array
    {
        $pending = $doctor->changeRequests()->pending()->latest('id')->first();
        $recent = $doctor->changeRequests()
            ->whereIn('status', [DoctorChangeRequestStatus::Approved, DoctorChangeRequestStatus::Rejected])
            ->latest('reviewed_at')
            ->limit(self::RECENT_DECISIONS)
            ->get();

        $replyCounts = $doctor->reviews()
            ->approved()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when response_body is null then 1 else 0 end) as unanswered')
            ->selectRaw('sum(case when response_source = ? and response_status = ? then 1 else 0 end) as pending_replies', [
                ReviewResponseSource::Doctor->value,
                ReviewResponseStatus::Pending->value,
            ])
            ->first();

        $summary = ReviewSummary::for($doctor);

        return [
            'doctor' => (new ManagedDoctorResource($doctor))->resolve($request),
            'pending_change_request' => $pending ? (new DoctorChangeRequestResource($pending))->resolve($request) : null,
            'recent_change_requests' => DoctorChangeRequestResource::collection($recent)->resolve($request),
            'stats' => [
                'review_count' => $summary['count'],
                'average_rating' => $summary['average_rating'],
                'unanswered_reviews' => (int) ($replyCounts->unanswered ?? 0),
                'pending_replies' => (int) ($replyCounts->pending_replies ?? 0),
            ],
            'settings' => [
                'replies_require_moderation' => (bool) (SiteSetting::current()->doctor_replies_require_moderation ?? true),
            ],
            'options' => $this->options(),
        ];
    }

    /**
     * What the selects on the dashboard offer: published entries only.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $pairs = fn (string $model): array => $model::query()
            ->published()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();

        return [
            'specialties' => $pairs(Specialty::class),
            'languages' => $pairs(Language::class),
            'clinical_interests' => $pairs(ClinicalInterest::class),
            'procedures' => $pairs(Procedure::class),
            'facilities' => Facility::query()
                ->published()
                ->clinical()
                ->orderBy('name')
                ->get(['id', 'name', 'city'])
                ->map(fn (Facility $facility): array => [
                    'id' => (int) $facility->id,
                    'name' => (string) $facility->name,
                    'city' => $facility->city,
                ])
                ->all(),
            'days' => array_values(OfficeHours::DAY_OPTIONS),
        ];
    }
}
