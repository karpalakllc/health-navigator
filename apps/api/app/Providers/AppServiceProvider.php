<?php

namespace App\Providers;

use App\Models\TriageFlow;
use App\Observers\TriageFlowObserver;
use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        TriageFlow::observe(TriageFlowObserver::class);

        // Applies to registration and password reset. The breach check is production-only:
        // it calls the Have I Been Pwned range API, fails open on network error, and we do
        // not want an outbound dependency in local dev or the test suite.
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Baseline abuse ceiling for every v1 route.
        //
        // Authenticated callers are keyed per user: Laravel's middleware priority puts
        // AuthenticatesRequests ahead of ThrottleRequests, so the guard has already run
        // by the time this closure is invoked on an auth: route.
        //
        // Anonymous callers key on IP, and that IP is frequently *not* an end user:
        // the Next.js server renders every public page server-side, so all of that
        // traffic reaches us from one origin address with no per-visitor identity.
        // The ceiling therefore has to accommodate aggregate server-side rendering
        // for the whole site, which is why it is high — treat it as a runaway guard,
        // not as a per-visitor control. The per-visitor controls are the named
        // limiters below, which sit on the endpoints a browser calls directly.
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(300)->by('user:'.$request->user()->id)
                : Limit::perMinute(1200)->by('ip:'.$request->ip());
        });

        RateLimiter::for('api-login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($email !== '' ? $email : $request->ip()),
            ];
        });

        RateLimiter::for('api-reviews', function (Request $request) {
            $userId = $request->user()?->id;

            return [
                Limit::perHour(10)->by($userId ?? $request->ip()),
                Limit::perDay(20)->by($userId ?? $request->ip()),
            ];
        });

        RateLimiter::for('api-forum-topics', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perDay(5)->by($userId ?? $request->ip());
        });

        RateLimiter::for('api-forum-posts', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perDay(30)->by($userId ?? $request->ip());
        });

        RateLimiter::for('api-triage-sessions', function (Request $request) {
            return [
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        RateLimiter::for('api-triage-complete', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });
    }
}
