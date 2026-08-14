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
    | "*" trusts the immediate calling IP. That is correct when the app port
    | is only reachable through the edge. If the origin is publicly
    | reachable, set TRUSTED_PROXIES to the host's published CIDR ranges
    | instead, otherwise X-Forwarded-For becomes spoofable and the rate
    | limits become trivially bypassable.
    |
    */

    // No default on purpose. "*" trusts whoever is talking to us, which is correct
    // behind an edge that is the only route in — and actively harmful if the origin
    // is directly reachable, because then any client can set X-Forwarded-For and mint
    // itself a fresh bucket for every IP-keyed limiter, including the 5/min on login.
    // Leaving this unset degrades to the pre-C1 behaviour (limits collapse onto the
    // edge address) rather than to a bypass, so unset fails safe.
    'proxies' => env('TRUSTED_PROXIES'),

];
