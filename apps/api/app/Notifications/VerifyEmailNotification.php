<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Macedonian wording over Laravel's signed-URL verification link.
 *
 * The link points at the API (which owns signature validation) and the API then
 * redirects to the web app. Routing it through the SPA instead would mean
 * passing a signature the browser has to hand back intact, for no benefit.
 *
 * Queued, like every other mail the auth endpoints can trigger. Sent inline, SMTP
 * latency (and an SMTP failure, as a 500) showed up only for addresses that have
 * an account, which made register/resend an account-existence oracle by timing.
 * The URL is therefore built in the worker, from config('app.url') — the API's
 * own origin — and the expiry window starts when the mail is sent rather than
 * when it was requested. afterCommit so a rolled-back write never mails a link.
 */
class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @param  User  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $expire = config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Потврдете ја вашата е-адреса — Zdravje360')
            ->greeting('Здраво!')
            ->line('Некој ја искористи оваа е-адреса за да отвори сметка на Zdravje360.')
            ->action('Потврди ја е-адресата', $url)
            ->line("Линкот важи {$expire} минути.")
            ->line('Ако тоа не сте биле вие, слободно игнорирајте ја оваа порака — сметката нема да биде активирана.');
    }
}
