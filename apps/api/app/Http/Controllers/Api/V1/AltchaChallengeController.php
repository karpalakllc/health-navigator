<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Altcha\AltchaGuard;
use Illuminate\Http\JsonResponse;

/**
 * GET /v1/altcha/challenge: a fresh signed proof-of-work challenge for the
 * web's ALTCHA widget. Plain JSON in the shape the widget expects (no API
 * envelope) and never cached: every form gets its own challenge.
 */
class AltchaChallengeController extends Controller
{
    public function __invoke(AltchaGuard $guard): JsonResponse
    {
        return response()
            ->json($guard->challenge())
            ->header('Cache-Control', 'no-store, private');
    }
}
