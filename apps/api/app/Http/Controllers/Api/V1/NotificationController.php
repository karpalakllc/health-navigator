<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\ReviewReminder;
use App\Models\User;
use App\Support\Notifications\UnsubscribeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * W8-B „Известувања“: the member's in-app list, their e-mail switches, and
 * the signed one-click unsubscribe that works without signing in.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $paginator = MemberNotification::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MemberNotification $row): array => $row->toPayload())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'unread_count' => MemberNotification::query()
                    ->where('user_id', $user->getKey())
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    /** Marks every unread notification read (the list was opened). */
    public function markRead(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $updated = MemberNotification::query()
            ->where('user_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(['marked' => $updated]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return ApiResponse::success(NotificationPreference::for($this->user($request))->toPayload());
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $types = array_map(fn (NotificationType $type): string => $type->value, NotificationType::preferenceTypes());

        $validated = $request->validate([
            'email_enabled' => ['sometimes', 'boolean'],
            'types' => ['sometimes', 'array:'.implode(',', $types)],
            'types.*' => ['boolean'],
        ]);

        $preferences = NotificationPreference::for($user);

        if (array_key_exists('email_enabled', $validated)) {
            $preferences->email_enabled = (bool) $validated['email_enabled'];
        }

        foreach ($validated['types'] ?? [] as $type => $enabled) {
            $preferences->setAttribute((string) $type, (bool) $enabled);
        }

        $preferences->save();

        return ApiResponse::success($preferences->toPayload());
    }

    /** What an unsubscribe link would turn off (the confirmation page). */
    public function showUnsubscribe(Request $request): JsonResponse
    {
        $parsed = $this->parseToken($request);

        if ($parsed === null) {
            return ApiResponse::error(__('api.notifications.unsubscribe_invalid'), 404, code: 'notifications.unsubscribe_invalid');
        }

        return ApiResponse::success(['type' => $parsed['type']->value]);
    }

    /**
     * Turns the link's type off: a preference switch, or for a reminder
     * e-mail every pending reminder. Idempotent; never turns anything on.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $parsed = $this->parseToken($request);

        if ($parsed === null) {
            return ApiResponse::error(__('api.notifications.unsubscribe_invalid'), 404, code: 'notifications.unsubscribe_invalid');
        }

        ['user' => $user, 'type' => $type] = $parsed;

        if ($type === NotificationType::ReviewReminder) {
            ReviewReminder::query()->where('user_id', $user->getKey())->delete();
        } else {
            $preferences = NotificationPreference::for($user);
            $preferences->setAttribute($type->value, false);
            $preferences->save();
        }

        return ApiResponse::success(['type' => $type->value, 'unsubscribed' => true]);
    }

    /**
     * @return array{user: User, type: NotificationType}|null
     */
    private function parseToken(Request $request): ?array
    {
        $request->validate(['token' => ['required', 'string', 'max:200']]);

        return UnsubscribeToken::parse((string) $request->input('token'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
