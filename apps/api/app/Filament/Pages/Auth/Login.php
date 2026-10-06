<?php

namespace App\Filament\Pages\Auth;

use App\Support\EmailAddress;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * The admin sign-in, matching addresses the way every other entry point does.
 *
 * Addresses are stored normalised (User::email()), and the API's auth requests
 * normalise before they look anything up. Filament's stock page passed the
 * typed value straight to the guard, so "Ops@Example.com" was "no such account"
 * here while the API accepted it.
 *
 * It also drops "Remember me". The cookie that ticks issues lasts 400 days and
 * signs its holder back in after the session expires with neither the password
 * nor the second factor, which Filament asks for only on this form. Without the
 * field the base class's `$data['remember']` is never set (getState() returns
 * only what the schema declares), so the guard is never asked to remember;
 * IgnoreRememberMeCookie covers cookies issued before this change.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
            ]);
    }

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
