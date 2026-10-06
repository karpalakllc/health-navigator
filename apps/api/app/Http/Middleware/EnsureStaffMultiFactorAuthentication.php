<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Per-role stand-in for Filament's EnsureMultiFactorAuthenticationIsEnabled.
 *
 * Filament's "required" switch is panel-wide and is evaluated when routes are
 * registered, with no user in hand, so it cannot express "required for staff,
 * optional for community moderators". The panel therefore marks MFA as required
 * — which registers the set-up page and attaches this middleware to every panel
 * page — and this class decides per user (User::requiresMultiFactorAuthentication()).
 */
class EnsureStaffMultiFactorAuthentication
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User
            || ! $user->requiresMultiFactorAuthentication()
            || MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
