<?php

namespace App\Support\Ux;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The short-lived pass that lets a staff member see the heatmap overlay on the
 * public site (docs/ux-heatmaps.md).
 *
 * Minted on the admin „UX анализа“ page for the signed-in staff member and
 * carried in the link's fragment, so it never reaches a server log. The public
 * site holds no heatmap data: its overlay asks for cells with this token, and
 * every request re-checks the signature, the expiry and that the staff member
 * still has `analytics.view`.
 *
 * Format: base64url(json {u, e, d}) "." base64url(HMAC-SHA256), keyed from
 * APP_KEY under a purpose label so it cannot be confused with another HMAC.
 */
final class UxOverlayToken
{
    private const PURPOSE = 'ux-heatmap-overlay:v1';

    /** The longest window a link may show, in days. */
    public const MAX_DAYS = 180;

    public static function issue(User $user, int $days, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $payload = [
            'u' => $user->id,
            'e' => $now->copy()->addMinutes(max(1, (int) config('ux.overlay_ttl_minutes', 120)))->getTimestamp(),
            'd' => max(1, min(self::MAX_DAYS, $days)),
        ];

        $body = self::encode((string) json_encode($payload));

        return $body.'.'.self::encode(self::sign($body));
    }

    /**
     * The staff member and the window the token grants, or null for anything
     * forged, malformed, expired or no longer allowed.
     *
     * @return array{user: User, days: int}|null
     */
    public static function verify(?string $token, ?Carbon $now = null): ?array
    {
        if (! is_string($token) || strlen($token) > 512 || substr_count($token, '.') !== 1) {
            return null;
        }

        if ((string) config('app.key') === '') {
            return null;
        }

        [$body, $signature] = explode('.', $token);
        $expected = self::encode(self::sign($body));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = base64_decode(strtr($body, '-_', '+/'), true);
        $payload = $decoded === false ? null : json_decode($decoded, true);

        if (! is_array($payload) || ! is_int($payload['u'] ?? null) || ! is_int($payload['e'] ?? null) || ! is_int($payload['d'] ?? null)) {
            return null;
        }

        if ($payload['e'] < ($now ?? Carbon::now())->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($payload['u']);

        if (! $user instanceof User || $user->suspended_at !== null || ! $user->can('analytics.view')) {
            return null;
        }

        return ['user' => $user, 'days' => max(1, min(self::MAX_DAYS, $payload['d']))];
    }

    private static function sign(string $body): string
    {
        $appKey = (string) config('app.key');

        // Without APP_KEY anyone could compute the signature.
        if ($appKey === '') {
            throw new \RuntimeException('APP_KEY is not set; heatmap overlay tokens cannot be signed.');
        }

        $key = hash_hmac('sha256', self::PURPOSE, $appKey, true);

        return hash_hmac('sha256', $body, $key, true);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
