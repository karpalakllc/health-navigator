<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Support\FrontendUrl;

/**
 * The signed one-click unsubscribe link in every member notification e-mail
 * (plan item G4). Stateless: "{user id}.{type}.{signature}", the signature an
 * HMAC-SHA256 under the app key, so the link works without signing in and
 * cannot be forged for another member or another type. It never expires —
 * an old e-mail's link must keep working, also after an APP_KEY rotation
 * (APP_PREVIOUS_KEYS) — and only ever turns something off.
 */
final class UnsubscribeToken
{
    public static function make(User $user, NotificationType $type): string
    {
        $payload = $user->getKey().'.'.$type->value;

        return $payload.'.'.self::sign($payload);
    }

    /**
     * @return array{user: User, type: NotificationType}|null
     */
    public static function parse(string $token): ?array
    {
        if (strlen($token) > 200 || preg_match('/^(\d{1,18})\.([a-z_]{1,40})\.([A-Za-z0-9_-]{43})$/', $token, $parts) !== 1) {
            return null;
        }

        if (! self::verifies($parts[1].'.'.$parts[2], $parts[3])) {
            return null;
        }

        $type = NotificationType::tryFrom($parts[2]);

        if ($type === null || ! $type->isUnsubscribable()) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->find((int) $parts[1]);

        if ($user === null || $user->isAnonymised()) {
            return null;
        }

        return ['user' => $user, 'type' => $type];
    }

    /** The web page that confirms with one click (and links to the settings). */
    public static function pageUrl(User $user, NotificationType $type): string
    {
        return FrontendUrl::to('/unsubscribe?token='.rawurlencode(self::make($user, $type)));
    }

    /** RFC 8058 List-Unsubscribe target: the mail client POSTs to it. */
    public static function oneClickUrl(User $user, NotificationType $type): string
    {
        return FrontendUrl::to('/api/notifications/unsubscribe?token='.rawurlencode(self::make($user, $type)));
    }

    /**
     * Signed under the current app key or one of APP_PREVIOUS_KEYS: rotating
     * the key (infra/deploy.md) must not break the links already sent.
     */
    private static function verifies(string $payload, string $signature): bool
    {
        $keys = [(string) config('app.key'), ...array_map('strval', (array) config('app.previous_keys', []))];
        $valid = false;

        foreach (array_filter($keys, fn (string $key): bool => $key !== '') as $key) {
            // Every key is checked (no early exit), in constant time each.
            $valid = hash_equals(self::sign($payload, $key), $signature) || $valid;
        }

        return $valid;
    }

    private static function sign(string $payload, ?string $key = null): string
    {
        $mac = hash_hmac('sha256', 'unsubscribe|'.$payload, $key ?? (string) config('app.key'), true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}
