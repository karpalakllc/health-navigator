<?php

namespace App\Providers;

use App\Models\TriageFlow;
use App\Observers\TriageFlowObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
