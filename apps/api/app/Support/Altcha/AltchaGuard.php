<?php

namespace App\Support\Altcha;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * ALTCHA proof of work (altcha-org/altcha, MIT), self-hosted: this class
 * issues signed challenges and checks the solutions the web widget sends
 * back. Nothing leaves the server and nothing is stored except, once a
 * solution is accepted, a hash of its challenge's signature in the cache
 * until the challenge would have expired — so one solution opens one form
 * submission, never two.
 *
 * Challenges use the library's deterministic mode: the server picks the
 * counter, so the browser does a known amount of work and checking a
 * solution is one HMAC (`keySignature`), not a re-run of the work.
 *
 * The challenge carries its issue time (`data.iat`, signed with the rest),
 * so a solution returned faster than a person can fill a form is refused.
 */
class AltchaGuard
{
    /** The request field the web relays the widget's base64 payload in. */
    public const FIELD = 'altcha';

    /** Longest payload accepted; a real one is well under 1 KB. */
    private const MAX_PAYLOAD_LENGTH = 4096;

    public function enabled(): bool
    {
        return (bool) config('zdravje.altcha.enabled', true);
    }

    /**
     * A fresh challenge, in the JSON shape the widget fetches.
     *
     * @return array<string, mixed>
     */
    public function challenge(): array
    {
        $min = (int) config('zdravje.altcha.counter_min');
        $max = max($min + 1, (int) config('zdravje.altcha.counter_max'));
        $now = now()->getTimestamp();

        return $this->altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: (int) config('zdravje.altcha.cost'),
            counter: random_int($min, $max),
            expiresAt: $now + 60 * (int) config('zdravje.altcha.expires_minutes'),
            data: ['iat' => $now],
        ))->toArray();
    }

    /**
     * Check a payload and spend it. Every outcome but Verified refuses the
     * request; the reason is for tests and logs, the visitor sees one message.
     */
    public function verify(mixed $payload): AltchaOutcome
    {
        if (! is_string($payload) || $payload === '') {
            return AltchaOutcome::Missing;
        }

        if (strlen($payload) > self::MAX_PAYLOAD_LENGTH) {
            return AltchaOutcome::Invalid;
        }

        try {
            $parsed = Payload::fromBase64($payload);
            $result = $this->altcha()->verifySolution(new VerifySolutionOptions($parsed, new Pbkdf2));
        } catch (InvalidArgumentException|\ValueError|\TypeError) {
            return AltchaOutcome::Invalid;
        }

        if ($result->expired) {
            return AltchaOutcome::Expired;
        }

        if (! $result->verified) {
            return AltchaOutcome::Invalid;
        }

        $parameters = $parsed->challenge->parameters;
        $issuedAt = $parameters->data['iat'] ?? null;

        // Every challenge this server issues carries iat; one without it was
        // not issued here (or by an older build) and is refused.
        if (! is_int($issuedAt)) {
            return AltchaOutcome::Invalid;
        }

        if (now()->getTimestamp() - $issuedAt < (int) config('zdravje.altcha.min_fill_seconds')) {
            return AltchaOutcome::TooFast;
        }

        // Spend it: the first request to add the key wins, a replay finds it.
        // Kept until the challenge expires, after which it is refused anyway.
        $ttl = max(60, (int) ceil((float) $parameters->expiresAt) - now()->getTimestamp() + 60);
        $key = 'altcha:spent:'.hash('sha256', (string) $parsed->challenge->signature);

        if (! Cache::add($key, true, $ttl)) {
            return AltchaOutcome::Replayed;
        }

        return AltchaOutcome::Verified;
    }

    private function altcha(): Altcha
    {
        $key = $this->hmacKey();

        return new Altcha(
            hmacSignatureSecret: $key,
            // A separate secret for the derived-key signature, so knowing
            // one HMAC tells nothing about the other.
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key-signature', $key),
        );
    }

    /**
     * ALTCHA_HMAC_KEY, or a key derived from APP_KEY so a fresh install is
     * protected without another secret to manage.
     */
    private function hmacKey(): string
    {
        $configured = config('zdravje.altcha.hmac_key');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return hash_hmac('sha256', 'altcha-challenge-signature', (string) config('app.key'));
    }
}
