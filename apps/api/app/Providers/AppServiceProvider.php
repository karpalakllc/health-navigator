<?php

namespace App\Providers;

use App\Http\Middleware\RejectInvalidUtf8;
use App\Http\Responses\ApiResponse;
use App\Models\TriageFlow;
use App\Models\User;
use App\Observers\TriageFlowObserver;
use App\Policies\RolePolicy;
use App\Support\DeploymentEnvironment;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
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

        // Staff sign in through the 2FA-protected panel, and API login refuses them
        // (auth.staff_use_admin). Apply the same rule to tokens they already hold:
        // one issued before the account gained admin.access is refused (not
        // deleted) for as long as it holds it, and works again after demotion
        // until it expires. Only requiresMultiFactorAuthentication() is consulted —
        // a community moderator's opt-in second factor protects the panel only,
        // and the secret need not be decrypted on every request. That check costs
        // a constant two permission queries per token-authenticated request
        // (Spatie resolves admin.access through the user's roles and direct
        // permissions; the permission list itself is cached).
        //
        // Suspension (D7) works the same way: a suspended account's tokens are
        // refused from the next request on, without being deleted, so that
        // unsuspending restores the member's sessions. The flag is a column on
        // the already-loaded tokenable, so it costs no extra query, and it is
        // checked first.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && ! ($token->tokenable instanceof User
                    && ($token->tokenable->isSuspended()
                        || $token->tokenable->isAnonymised()
                        || $token->tokenable->requiresMultiFactorAuthentication())),
        );

        // Pushed here rather than in bootstrap/app.php: it joins the api group
        // after SetApiLocale, so its 422 message is already localised.
        $this->app->make(Router::class)->pushMiddlewareToGroup('api', RejectInvalidUtf8::class);

        // Applies to registration, password reset and the bootstrap admin. The breach
        // check runs in every deployed environment (staging accounts are real ones
        // too): it calls the Have I Been Pwned range API and fails open on network
        // error. Local dev and the test suite skip it to stay offline.
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            return DeploymentEnvironment::isDeployed() ? $rule->uncompromised() : $rule;
        });

        // Baseline abuse ceiling for every v1 route.
        //
        // Authenticated callers are keyed per user: Laravel's middleware priority puts
        // AuthenticatesRequests ahead of ThrottleRequests, so the guard has already run
        // by the time this closure is invoked on an auth: route.
        //
        // Anonymous callers key on IP. Server-side rendering reaches us from the
        // Next.js server, which vouches for the visitor's address with
        // WEB_TIER_SECRET (TrustWebTierClientIp), so with that set this is per
        // visitor. Without it — a misconfigured deploy, or cached fetches such as
        // the sitemap that deliberately carry no visitor — everything rendered
        // server-side shares the web tier's address, which is why the ceiling stays
        // high: treat it as a runaway guard, not as a per-visitor control. The per-visitor controls are the named
        // limiters below, which sit on the endpoints a browser calls directly.
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(300)->by('user:'.$request->user()->id)
                : Limit::perMinute(1200)->by('ip:'.$request->ip());
        });

        // Address-level backstop only. The per-account control lives in
        // AuthController because it must count *failures*, not requests: a
        // middleware limit keyed on the submitted email lets anyone who knows an
        // address hold that account in permanent lockout by sending requests.
        //
        // This is deliberately loose. It depends on $request->ip() being the
        // visitor, which depends on the web tier vouching for the address with
        // WEB_TIER_SECRET (TrustWebTierClientIp) — so if a deployment gets that
        // wrong, this throttles a shared origin instead of locking the platform out.
        RateLimiter::for('api-login', function (Request $request) {
            return Limit::perMinute(40)->by('login-ip:'.$request->ip());
        });

        // Address-level cooldown lives in VerificationMailer, so it applies to every
        // path that can send a link rather than just this route — /auth/register
        // used to walk straight past a route-level limit. What remains here is the
        // per-caller ceiling on hammering the endpoint itself.
        RateLimiter::for('api-verification-resend', function (Request $request) {
            return Limit::perMinute(10)->by('resend-ip:'.$request->ip());
        });

        // The username is what every review and forum post shows; renaming is
        // limited to once in 90 days anyway, so a burst of attempts is probing.
        RateLimiter::for('api-profile', function (Request $request) {
            return Limit::perHour(10)->by('profile:'.($request->user()->id ?? $request->ip()));
        });

        // Availability checks while typing a username: generous enough for a
        // debounced field, too tight to walk the namespace.
        RateLimiter::for('api-username-check', function (Request $request) {
            return [
                Limit::perMinute(30)->by('username-check:'.$request->ip()),
                Limit::perDay(500)->by('username-check-day:'.$request->ip()),
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

        // Reads every row the member owns; a handful a day is plenty.
        RateLimiter::for('api-account-export', function (Request $request) {
            return Limit::perHour(5)
                ->by('account-export:'.($request->user()->id ?? $request->ip()))
                ->response(fn (Request $request, array $headers) => ApiResponse::errorCode('account.export_throttled', 429)
                    ->withHeaders($headers));
        });

        // Account deletion checks the password: bound the guesses a stolen token buys.
        RateLimiter::for('api-account-delete', function (Request $request) {
            return Limit::perHour(5)->by('account-delete:'.($request->user()->id ?? $request->ip()));
        });

        // Guidance is called from the browser directly, so these see a real client
        // address without depending on the web tier forwarding one.
        RateLimiter::for('api-triage-sessions', function (Request $request) {
            return [
                Limit::perHour(10)->by('triage:'.$request->ip()),
            ];
        });

        RateLimiter::for('api-triage-complete', function (Request $request) {
            return Limit::perHour(5)->by('triage-complete:'.$request->ip());
        });
    }
}
