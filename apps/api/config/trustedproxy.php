<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The API sits behind a TLS-terminating edge in staging and production
    | (Forge / Railway / Fly — see infra/deploy.md). Laravel must trust that
    | edge before it will read X-Forwarded-For, and until it does,
    | $request->ip() returns the proxy address for every visitor — which
    | silently collapses every IP-keyed rate limiter in AppServiceProvider
    | into a single global bucket.
    |
    | List the exact CIDR ranges of the edge and the web tier. Never "*",
    | "**" or 0.0.0.0/0: with every hop trusted, Laravel walks the whole
    | X-Forwarded-For chain and takes the leftmost entry — the one the client
    | wrote — as $request->ip(). That holds even when the origin is reachable
    | only through the edge, because the edge appends to the client's header
    | rather than replacing it. Any caller then picks its own bucket for every
    | IP-keyed limiter. `php artisan platform:preflight` rejects those values.
    |
    */

    // No default on purpose. Leaving this unset degrades to the pre-C1 behaviour
    // (limits collapse onto the edge address) rather than to a bypass, so unset
    // fails safe — but preflight still fails the deploy until it is set.
    'proxies' => env('TRUSTED_PROXIES'),

];
