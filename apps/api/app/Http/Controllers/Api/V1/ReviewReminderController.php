<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\ReviewReminder;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Notifications\ProfileRef;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * W8-B „Потсети ме за 2 недели“: the member's explicit, per-profile request
 * for one reminder e-mail. Storing it is the opt-in; cancelling deletes it.
 */
class ReviewReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $reminders = ReviewReminder::query()
            ->where('user_id', $user->getKey())
            ->with('reviewable')
            ->orderBy('remind_at')
            ->get();

        return response()->json([
            'data' => $reminders
                ->map(fn (ReviewReminder $reminder): array => $this->payload($reminder))
                ->filter(fn (array $row): bool => $row['profile'] !== null)
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:doctor,facility,pharmacy'],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $profile = $this->profile($validated['kind'], $validated['slug']);

        if ($profile instanceof Doctor && $profile->isOwnedBy($user)) {
            throw ValidationException::withMessages(['reminder' => [__('api.reminder.own_profile')]]);
        }

        $reviewed = Review::query()
            ->where('user_id', $user->getKey())
            ->where('reviewable_type', $profile::class)
            ->where('reviewable_id', $profile->getKey())
            ->exists();

        if ($reviewed) {
            throw ValidationException::withMessages(['reminder' => [__('api.reminder.already_reviewed')]]);
        }

        $existing = ReviewReminder::query()
            ->where('user_id', $user->getKey())
            ->where('reviewable_type', $profile::class)
            ->where('reviewable_id', $profile->getKey())
            ->first();

        if ($existing === null && ReviewReminder::query()->where('user_id', $user->getKey())->count() >= ReviewReminder::MAX_PENDING) {
            throw ValidationException::withMessages(['reminder' => [__('api.reminder.too_many')]]);
        }

        $reminder = ReviewReminder::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'reviewable_type' => $profile::class,
                'reviewable_id' => $profile->getKey(),
            ],
            ['remind_at' => now()->addDays(ReviewReminder::DELAY_DAYS)],
        );
        $reminder->setRelation('reviewable', $profile);

        return ApiResponse::success($this->payload($reminder), $existing === null ? 201 : 200);
    }

    public function destroy(int $reminder, Request $request): JsonResponse
    {
        ReviewReminder::query()
            ->where('user_id', $this->user($request)->getKey())
            ->whereKey($reminder)
            ->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function profile(string $kind, string $slug): Doctor|Facility
    {
        return match ($kind) {
            'doctor' => Doctor::query()->published()->where('slug', $slug)->firstOrFail(),
            'facility' => Facility::query()->published()->clinical()->where('slug', $slug)->firstOrFail(),
            default => SiteSetting::current()->public_pharmacies
                ? Facility::query()->published()->pharmacy()->where('slug', $slug)->firstOrFail()
                : abort(404),
        };
    }

    /**
     * @return array{id: int, profile: array{kind: string, slug: string, name: string, path: string}|null, remind_at: string, created_at: string|null}
     */
    private function payload(ReviewReminder $reminder): array
    {
        return [
            'id' => (int) $reminder->getKey(),
            'profile' => ProfileRef::for($reminder->reviewable),
            'remind_at' => $reminder->remind_at->toIso8601String(),
            'created_at' => $reminder->created_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
