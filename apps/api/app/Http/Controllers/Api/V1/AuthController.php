<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\FrontendUrl;
use App\Support\VerificationMailer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    /** Failed sign-ins tolerated per account before it is briefly locked. */
    private const LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function login(LoginRequest $request): JsonResponse
    {
        // Counts failures, not requests — and keys on address *and* caller.
        //
        // Counting requests lets anyone who knows an address hold the owner out by
        // sending traffic. Keying on the address alone has the same effect one step
        // removed: five wrong passwords a minute from anywhere refuse the owner's
        // correct one, indefinitely. Pairing the address with the caller means an
        // attacker only ever locks out themselves, which is the trade Fortify makes.
        //
        // Volume from one source is bounded separately by the api-login limiter, and
        // spraying one account from many addresses is what the audit log is for.
        $throttleKey = 'login:'.sha1(
            mb_strtolower(trim($request->string('email')->toString())).'|'.$request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            // Retry-After, and an X-RateLimit-* set describing *this* limiter.
            // Without them the only rate-limit headers on the response come from
            // the route's throttle middleware, which is not the limiter that
            // rejected the request — it advertises the remaining budget of a
            // bucket that still has room, which reads as "try again now".
            return ApiResponse::errorCode('errors.too_many_requests', 429, errors: [
                'email' => [__('api.auth.throttled', ['seconds' => $retryAfter])],
            ])->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => self::LOGIN_ATTEMPTS,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid_credentials')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Only reachable with correct credentials, so this cannot be used to probe
        // which addresses exist — the caller already proved they own the account.
        if (! $user->hasVerifiedEmail()) {
            return ApiResponse::errorCode('auth.email_unverified', 403);
        }

        $tokenName = $request->string('device_name')->toString() ?: 'api';
        $token = $user->createToken($tokenName);

        $this->analytics->record('user.login', $user);

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve($request),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Registration is verify-then-activate, and deliberately tells the caller
     * nothing about whether the address is already registered.
     *
     * A signup endpoint that returns a session on success is an account-existence
     * oracle by construction — success itself is the signal. The only way to close
     * that is to make success mean "we have sent an email", identically in both
     * cases, and to move account activation behind proof of address ownership.
     *
     * Both branches hash a password so the two paths do not separate on timing.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();

        // Always pay the bcrypt cost, whichever branch we take. Note this only
        // equalises the branches when mail is queued out-of-process: under
        // QUEUE_CONNECTION=sync the existing-address branch also sends inline.
        // Production uses redis (infra/env.production.example).
        $hashedPassword = Hash::make($password);

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $this->notifyExistingAccount($existing);

            return $this->registrationAccepted();
        }

        try {
            $user = User::query()->create([
                'name' => $request->string('name')->toString(),
                'email' => $email,
                'password' => $hashedPassword,
                'role' => UserRole::Member,
                'user_kind' => UserKind::Client,
                'email_verified_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent signup for the same address. Treat it
            // exactly like the "already registered" branch above.
            $raced = User::query()->where('email', $email)->first();

            if ($raced !== null) {
                $this->notifyExistingAccount($raced);
            }

            return $this->registrationAccepted();
        }

        VerificationMailer::sendVerificationLink($user);

        $this->analytics->record('user.registration_started', $user);

        return $this->registrationAccepted();
    }

    /**
     * Verify an address from the signed link in the verification email.
     *
     * Redirects into the web app rather than returning JSON: this URL is opened
     * by a mail client, not by our own fetch layer.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);

        if ($user === null || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away(FrontendUrl::to('/verify-email?status=invalid'));
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away(FrontendUrl::to('/verify-email?status=already'));
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->analytics->record('user.registered', $user);

        Mail::to($user)->queue(new WelcomeMail(
            recipientName: $user->name,
            loginUrl: FrontendUrl::to('/login'),
        ));

        return redirect()->away(FrontendUrl::to('/verify-email?status=verified'));
    }

    /**
     * Re-send the verification link. Same non-committal response in every case,
     * for the same reason register() has one.
     */
    public function resendVerification(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user !== null) {
            VerificationMailer::sendVerificationLink($user);
        }

        return $this->registrationAccepted();
    }

    private function notifyExistingAccount(User $user): void
    {
        // Unverified accounts get another verification link rather than a
        // "you already have an account" message they cannot act on.
        if (! $user->hasVerifiedEmail()) {
            VerificationMailer::sendVerificationLink($user);

            return;
        }

        // Through the same gate as the verification link: this is triggerable by
        // anyone typing someone else's address into the sign-up form, so it has to
        // be metered per address rather than relying on whatever limit happens to
        // sit on the route.
        VerificationMailer::sendAccountExistsNotice($user);
    }

    private function registrationAccepted(): JsonResponse
    {
        return ApiResponse::success([
            'message' => __('api.auth.registration_pending'),
        ], 202);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email'),
        );

        // Always answer identically. Reporting "we can't find that email" turned
        // this into an unauthenticated oracle for whether a given person has an
        // account on a health platform. Genuine failures are still reported to
        // the error tracker rather than to the caller.
        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            report(new RuntimeException("Password reset link failed with status [{$status}]."));
        }

        return ApiResponse::success(['message' => __(Password::RESET_LINK_SENT)]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return ApiResponse::success(['message' => __($status)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success(['message' => __('api.auth.logged_out')]);
    }
}
