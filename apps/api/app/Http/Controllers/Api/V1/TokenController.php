<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The member's signed-in devices (D6): one Sanctum token per sign-in, named at
 * login from `device_name` (the web tier sends a coarse "browser · OS" label,
 * never the raw user agent).
 *
 * Only the caller's own tokens are ever listed or touched: a token id that
 * belongs to someone else answers 404, the same as one that does not exist.
 */
class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $currentId = $this->currentTokenId($request);

        $tokens = $this->activeTokens($user)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'created_at', 'last_used_at', 'expires_at'])
            // The device in hand first, then most recently used.
            ->sortByDesc(fn (PersonalAccessToken $token): bool => $token->getKey() === $currentId)
            ->values()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->getKey(),
                'name' => $token->name,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $this->expiresAt($token)?->toIso8601String(),
                'is_current' => $token->getKey() === $currentId,
            ]);

        return ApiResponse::success(['tokens' => $tokens->all()]);
    }

    /**
     * Sign one device out. The current one may be revoked too (it is the same
     * as logging out); the web UI offers "sign out" for it instead.
     */
    public function destroy(Request $request, int $token): JsonResponse
    {
        $deleted = $this->user($request)->tokens()->whereKey($token)->delete();

        if ($deleted === 0) {
            return ApiResponse::errorCode('errors.not_found', 404);
        }

        return ApiResponse::success(['message' => __('api.account.token_revoked')]);
    }

    /**
     * Sign out every device except the one making the request.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $query = $this->user($request)->tokens();
        $currentId = $this->currentTokenId($request);

        if ($currentId !== null) {
            $query->whereKeyNot($currentId);
        }

        $revoked = $query->delete();

        return ApiResponse::success([
            'message' => __('api.account.tokens_revoked'),
            'revoked' => $revoked,
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Null when the request was not made with a personal access token (a
     * Sanctum TransientToken on a first-party session request).
     */
    private function currentTokenId(Request $request): ?int
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;
    }

    /**
     * Tokens Sanctum would still accept: within the global expiry window and
     * their own expires_at. Expired rows linger until the daily
     * sanctum:prune-expired run and are not devices any more.
     *
     * @return Builder<PersonalAccessToken>
     */
    private function activeTokens(User $user): Builder
    {
        /** @var Builder<PersonalAccessToken> $query */
        $query = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        $minutes = config('sanctum.expiration');

        if ($minutes) {
            $query->where('created_at', '>', now()->subMinutes((int) $minutes));
        }

        return $query;
    }

    /**
     * Whichever comes first: the token's own expiry or the global window.
     */
    private function expiresAt(PersonalAccessToken $token): ?CarbonInterface
    {
        $minutes = config('sanctum.expiration');
        $window = $minutes && $token->created_at ? $token->created_at->copy()->addMinutes((int) $minutes) : null;

        if ($token->expires_at === null) {
            return $window;
        }

        return $window === null || $token->expires_at->lt($window) ? $token->expires_at : $window;
    }
}
