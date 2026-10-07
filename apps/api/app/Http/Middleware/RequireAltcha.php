<?php

namespace App\Http\Middleware;

use App\Support\Altcha\AltchaGuard;
use App\Support\Altcha\AltchaOutcome;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * `altcha`: the request must carry a solved, unspent ALTCHA challenge in the
 * `altcha` field (AltchaGuard). Listed after the route's throttles, so a
 * refused attempt still counts against them. A refusal is a 422 on the
 * `altcha` field with one message whatever the reason, so the answer does
 * not coach a bot.
 */
class RequireAltcha
{
    public function __construct(private readonly AltchaGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->guard->enabled()) {
            return $next($request);
        }

        $outcome = $this->guard->verify($request->input(AltchaGuard::FIELD));

        if ($outcome !== AltchaOutcome::Verified) {
            throw ValidationException::withMessages([
                AltchaGuard::FIELD => [__('api.altcha.failed')],
            ]);
        }

        return $next($request);
    }
}
