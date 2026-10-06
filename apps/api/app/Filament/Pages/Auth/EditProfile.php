<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * The panel's account page, reduced to two-factor management.
 *
 * Filament's stock profile also edits name, email and password. Those are
 * administered elsewhere (the staff/client resources, the password-reset flow),
 * with rules this page would bypass — email normalisation, contested
 * registrations, API token revocation on a password change — so the page shows
 * only the authenticator-app section: set up, regenerate recovery codes, and
 * disable, each of which asks for the current password (Filament 5.8.2+).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // Community moderators enrol by choice, and might expect it to cover
            // their public account too. It does not: API login and tokens are
            // held to two-factor only for accounts that require it (admin.access).
            Text::make('Two-factor authentication protects your access to this moderation panel. Signing in to the public website still uses your password.')
                ->visible(fn (): bool => ($user = Filament::auth()->user()) instanceof User && $user->isCommunityModeratorOnly()),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
        ]);
    }

    /**
     * Unreachable from the page (there is no form), but a Livewire call can name
     * any public method; make sure this one cannot write to the account.
     */
    public function save(): void {}
}
