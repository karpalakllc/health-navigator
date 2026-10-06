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

    /**
     * Bcrypt hash of a throwaway string, at cost 12 (BCRYPT_ROUNDS in every
     * deployed environment). Checked against when the address has no account so
     * that branch costs the same as a wrong password. Matches nothing real.
     */
    private const TIMING_EQUALISER_HASH = '$2y$12$kOBD7Q0gtjx9c85wxOWxXOcWCnR2K4svzU66W3Q9G73w0fDy.vwry';

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

        // Always pay for one bcrypt verification. Skipping it for unknown
        // addresses made "no such account" answer measurably faster than "wrong
        // password", which is an account-existence oracle by timing.
        $passwordMatches = Hash::check(
            $request->string('password')->toString(),
            $user !== null ? $user->password : self::TIMING_EQUALISER_HASH,
        );

        if ($user === null || ! $passwordMatches) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid_credentials')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // The admin panel requires a second factor of these accounts; a token
        // minted here on the password alone would carry the same staff powers
        // (forum moderation through the policies) without it, so staff work in
        // the panel. Two-factor protects the panel only: a community moderator
        // who opted in still signs in to the website with their password. Only
        // reachable with the right password, like the check below, so it
        // reveals nothing the caller has not already proved.
        if ($user->requiresMultiFactorAuthentication()) {
            return ApiResponse::errorCode('auth.staff_use_admin', 403);
        }

        // Only reachable with correct credentials, so this cannot be used to probe
        // which addresses exist — the caller already proved they own the account.
        //
        // The link is sent from the owner's own mail budget: the public one can be
        // spent by anyone who knows the address (see VerificationMailer).
        if (! $user->hasVerifiedEmail()) {
            VerificationMailer::sendVerificationLinkToOwner($user);

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

        $name = $request->string('name')->toString();

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $this->notifyExistingAccount($existing);

            return $this->registrationAccepted();
        }

        try {
            $user = User::query()->create([
                'name' => $name,
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

        if ($user->registration_contested_at !== null) {
            // Lost the race to a concurrent hit on the same link, which has
            // already settled the account: report it as already verified.
            if (! $this->settleContestedRegistration($user)) {
                return redirect()->away(FrontendUrl::to('/verify-email?status=already'));
            }

            return redirect()->away(FrontendUrl::to('/verify-email?status=verified_set_password'));
        }

        // Same guard as a contested settlement: a double click, or a password
        // reset verifying the address meanwhile, also read it as unverified, and
        // only the request that flips it announces it. A sign-up that contested
        // the address in between leaves it for the next hit to settle instead.
        $verifiedAt = $user->freshTimestamp();

        $verified = User::query()
            ->whereKey($user->getKey())
            ->whereNull('email_verified_at')
            ->whereNull('registration_contested_at')
            ->toBase()
            ->update([
                'email_verified_at' => $verifiedAt,
                'updated_at' => $verifiedAt,
            ]);

        if ($verified !== 1) {
            return redirect()->away(FrontendUrl::to('/verify-email?status=already'));
        }

        $user->forceFill([
            'email_verified_at' => $verifiedAt,
            'updated_at' => $verifiedAt,
        ])->syncOriginal();

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

    /**
     * A sign-up for an address that already has an account never changes that
     * account's name or password — whether it is verified or not, client or
     * staff. The response is the same 202 in every case; only the mail differs.
     *
     * An unverified client account gets another verification link (a "you
     * already have an account" notice is useless to someone who cannot sign in
     * yet) and is marked contested: two parties have now claimed the address, and
     * the stored password may belong to either. See settleContestedRegistration()
     * for how verification resolves that.
     *
     * Staff accounts and accounts holding a role are never "pending" in this
     * sense — they are created by an administrator, verified from creation, and
     * nobody signs up for them. They get the account-exists notice like any
     * verified account, so the public form can neither mail them a link nor
     * touch their state.
     */
    private function notifyExistingAccount(User $user): void
    {
        if (! $user->hasVerifiedEmail() && $user->isClient() && ! $user->roles()->exists()) {
            if ($user->registration_contested_at === null) {
                $user->forceFill(['registration_contested_at' => now()])->save();
            }

            VerificationMailer::sendVerificationLink($user);

            return;
        }

        // Through the same gate as the verification link: this is triggerable by
        // anyone typing someone else's address into the sign-up form, so it has to
        // be metered per address rather than relying on whatever limit happens to
        // sit on the route.
        VerificationMailer::sendAccountExistsNotice($user);
    }

    /**
     * Account pre-hijacking defence: verifying a contested registration confirms
     * the address but does not activate the password stored with it.
     *
     * Once two sign-ups have named the same unverified address, the stored
     * password is whichever one was kept — the first registrant's — and nothing
     * the server holds says whether that was the mailbox owner. Clicking the link
     * proves control of the mailbox, not authorship of that password. So the
     * stored password is replaced with an unusable random one, any tokens are
     * revoked, and a password-reset link goes to the address: whoever controls
     * the mailbox ends up choosing the password, regardless of who registered
     * first or last. Re-registering again cannot invalidate a link the owner
     * already holds, and cannot bind a link to anyone's password, because links
     * no longer carry a password at all.
     *
     * Residual: someone who registers an address its owner never registers
     * leaves an uncontested account whose link only the owner receives. Unless
     * the owner clicks a link for a sign-up they did not make — the mail tells
     * them to ignore it — the account stays inactive, and the owner can claim it
     * at any time through password reset.
     *
     * Settles at most once. Two hits on the same link (a double click, a mail
     * scanner racing the owner) both read the account as contested; the guarded
     * UPDATE lets exactly one of them flip it, and only that one replaces the
     * password and mails a reset — a second reset mail would kill the token in
     * the first. Returns false for the request that lost.
     */
    private function settleContestedRegistration(User $user): bool
    {
        $verifiedAt = $user->freshTimestamp();
        $password = Hash::make(Str::random(64));
        $rememberToken = Str::random(60);

        $settled = User::query()
            ->whereKey($user->getKey())
            ->whereNotNull('registration_contested_at')
            ->whereNull('email_verified_at')
            ->toBase()
            ->update([
                'email_verified_at' => $verifiedAt,
                'password' => $password,
                'remember_token' => $rememberToken,
                'registration_contested_at' => null,
                'updated_at' => $verifiedAt,
            ]);

        if ($settled !== 1) {
            return false;
        }

        $user->forceFill([
            'email_verified_at' => $verifiedAt,
            'password' => $password,
            'remember_token' => $rememberToken,
            'registration_contested_at' => null,
            'updated_at' => $verifiedAt,
        ])->syncOriginal();

        $user->revokeApiTokens();

        event(new Verified($user));
        $this->analytics->record('user.registered', $user);

        // Created directly rather than through Password::sendResetLink(), whose
        // per-address throttle could silently drop this one — and without it the
        // owner of a now password-less account has nothing in their inbox.
        $user->sendPasswordResetNotification(Password::createToken($user));

        return true;
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

        // For a real account the broker bcrypt-hashes the new token before storing
        // it; for an unknown address (or a throttled one) it returns straight away.
        // Pay the same hash here so the two do not separate on timing. What remains
        // is the token row's delete+insert and the queue push for real accounts —
        // single-digit milliseconds against a ~250ms hash and network jitter, and
        // not worth faking writes to remove.
        if ($status === Password::INVALID_USER || $status === Password::RESET_THROTTLED) {
            Hash::make(Str::random(40));
        }

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
                // Completing a reset proves control of the mailbox exactly as the
                // verification link does, and the password is the one the mailbox
                // owner just chose — so it also finishes a pending or contested
                // registration. Without this, an owner who used forgot-password
                // (their sign-up password never worked: an earlier registrant's
                // was kept) stayed unverified; logging in mailed a link whose click
                // settled the contest by wiping the password they had just chosen.
                $wasUnverified = ! $user->hasVerifiedEmail();

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? $user->freshTimestamp(),
                    'registration_contested_at' => null,
                ])->save();

                event(new PasswordReset($user));

                if ($wasUnverified) {
                    event(new Verified($user));
                    $this->analytics->record('user.registered', $user);
                }
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
