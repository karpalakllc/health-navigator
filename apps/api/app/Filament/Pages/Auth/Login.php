<?php

namespace App\Filament\Pages\Auth;

use App\Support\EmailAddress;
use Filament\Auth\Pages\Login as BaseLogin;
use SensitiveParameter;

/**
 * The admin sign-in, matching addresses the way every other entry point does.
 *
 * Addresses are stored normalised (User::email()), and the API's auth requests
 * normalise before they look anything up. Filament's stock page passed the
 * typed value straight to the guard, so "Ops@Example.com" was "no such account"
 * here while the API accepted it.
 */
class Login extends BaseLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $credentials = parent::getCredentialsFromFormData($data);
        $credentials['email'] = EmailAddress::normalize((string) $credentials['email']);

        return $credentials;
    }
}
