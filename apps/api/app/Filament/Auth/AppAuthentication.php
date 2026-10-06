<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication as BaseAppAuthentication;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Support\Enums\Size;

/**
 * Filament's authenticator-app provider with a set-up action that is hard to
 * miss.
 *
 * Stock Filament renders "Set up" as a small link, which staff overlooked on
 * the mandatory set-up page. Here it is a large, full-width primary button.
 * Only the presentation changes: the action, its modal and the stored secret
 * are Filament's own. Applies wherever the provider's management section is
 * shown — the required set-up page and the profile page.
 */
class AppAuthentication extends BaseAppAuthentication
{
    public function getManagementSchemaComponents(): array
    {
        $user = Filament::auth()->user();

        return array_map(
            // Full width only while set-up is the sole action; once enrolled,
            // the regenerate and disable actions keep their inline layout.
            fn ($component) => $component instanceof Actions
                ? $component->fullWidth(fn (): bool => ! $this->isEnabled($user))
                : $component,
            parent::getManagementSchemaComponents(),
        );
    }

    public function getActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action->getName() === 'setUpAppAuthentication'
                ? $action->button()->size(Size::Large)->label('Set up authenticator app')
                : $action,
            parent::getActions(),
        );
    }
}
