<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Macedonian wording over Laravel's signed-URL verification link.
 *
 * The link points at the API (which owns signature validation) and the API then
 * redirects to the web app. Routing it through the SPA instead would mean
 * passing a signature the browser has to hand back intact, for no benefit.
 */
class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $expire = config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Потврдете ја вашата е-адреса — Zdravje360')
            ->greeting('Здраво!')
            ->line('Некој ја користеше оваа е-адреса за да отвори сметка на Zdravje360.')
            ->action('Потврди ја е-адресата', $url)
            ->line("Линкот истекува за {$expire} минути.")
            ->line('Ако не сте вие, слободно игнорирајте ја оваа порака — сметката нема да биде активирана.');
    }
}
