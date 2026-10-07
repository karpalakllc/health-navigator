<?php

namespace App\Mail\Concerns;

use App\Enums\NotificationType;
use App\Models\User;

/** A member notification e-mail that carries an unsubscribe link (HasUnsubscribeLink). */
interface Unsubscribable
{
    public function withUnsubscribe(User $user, NotificationType $type): static;
}
