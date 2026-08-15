<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Negotiates the response language for API clients.
 *
 * Applied to the API group only. config('app.locale') stays `en` so the
 * Filament admin remains English for staff, while the public API answers in
 * Macedonian by default and honours Accept-Language for future clients
 * (the mobile app, and an English locale on the web).
 */
class SetApiLocale
{
    public const DEFAULT_LOCALE = 'mk';

    /** @var list<string> */
    public const SUPPORTED = ['mk', 'en'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED) ?? self::DEFAULT_LOCALE;

        App::setLocale($locale);

        $response = $next($request);

        // Responses vary by request header, so any shared cache must key on it.
        $response->headers->set('Content-Language', $locale);
        $vary = $response->headers->get('Vary');
        $response->headers->set(
            'Vary',
            $vary ? $vary.', Accept-Language' : 'Accept-Language',
        );

        return $response;
    }
}
