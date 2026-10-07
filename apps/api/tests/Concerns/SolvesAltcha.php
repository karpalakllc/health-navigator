<?php

namespace Tests\Concerns;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;

/**
 * Turns ALTCHA on with a tiny amount of work and solves challenges from
 * GET /api/v1/altcha/challenge the way the browser widget does, so a test
 * can send a genuine payload.
 */
trait SolvesAltcha
{
    protected function enableAltcha(int $minFillSeconds = 0): void
    {
        config([
            'zdravje.altcha.enabled' => true,
            'zdravje.altcha.cost' => 1,
            'zdravje.altcha.counter_min' => 0,
            'zdravje.altcha.counter_max' => 30,
            'zdravje.altcha.min_fill_seconds' => $minFillSeconds,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchAltchaChallenge(): array
    {
        return $this->getJson('/api/v1/altcha/challenge')->assertOk()->json();
    }

    /**
     * A solved payload, base64 as the widget posts it.
     *
     * @param  array<string, mixed>|null  $challenge
     */
    protected function solvedAltcha(?array $challenge = null): string
    {
        $challenge = Challenge::fromArray($challenge ?? $this->fetchAltchaChallenge());
        $solution = (new Altcha)->solveChallenge(new SolveChallengeOptions(
            algorithm: new Pbkdf2,
            challenge: $challenge,
        ));

        $this->assertNotNull($solution, 'The test challenge could not be solved.');

        return (new Payload($challenge, $solution))->toBase64();
    }
}
