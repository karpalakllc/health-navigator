<?php

namespace App\Mail\Concerns;

use App\Enums\NotificationType;
use App\Models\User;
use App\Support\FrontendUrl;
use App\Support\Notifications\UnsubscribeToken;
use Illuminate\Mail\Mailables\Headers;

/**
 * Member notification e-mails (W8-B, plan item G4): a signed one-click
 * unsubscribe link for this e-mail's type in the body
 * (mail/partials/unsubscribe) and RFC 8058 List-Unsubscribe headers, so mail
 * clients offer their own „Unsubscribe“ button.
 */
trait HasUnsubscribeLink
{
    public ?string $unsubscribeUrl = null;

    public ?string $unsubscribeOneClickUrl = null;

    public ?string $preferencesUrl = null;

    public ?string $unsubscribeType = null;

    public function withUnsubscribe(User $user, NotificationType $type): static
    {
        $this->unsubscribeUrl = UnsubscribeToken::pageUrl($user, $type);
        $this->unsubscribeOneClickUrl = UnsubscribeToken::oneClickUrl($user, $type);
        $this->preferencesUrl = FrontendUrl::to('/account/notifications');
        $this->unsubscribeType = $type->value;

        return $this;
    }

    public function headers(): Headers
    {
        if ($this->unsubscribeOneClickUrl === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeOneClickUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
