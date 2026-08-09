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
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid_credentials')],
            ]);
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

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
            // Verification is not implemented; recording an honest timestamp rather
            // than a null that nothing would ever clear. See docs/roadmap.md.
            'email_verified_at' => now(),
        ]);

        $tokenName = $request->string('device_name')->toString() ?: 'api';
        $token = $user->createToken($tokenName);

        $this->analytics->record('user.registered', $user);

        Mail::to($user)->queue(new WelcomeMail(
            recipientName: $user->name,
            loginUrl: FrontendUrl::to('/login'),
        ));

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve($request),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
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
