<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queued for the same reason as VerifyEmailNotification: sent inline, SMTP time
 * (or an SMTP failure, surfacing as a 500) only happened for addresses that have
 * an account, so forgot-password leaked existence by timing despite its uniform
 * body. The plain reset token travels in the queued payload, as it does for any
 * queued Laravel reset notification; the queue store is trusted infrastructure.
 */
class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] $token)
    {
        parent::__construct($token);

        $this->afterCommit();
    }

    /**
     * @return array<string, string>
     */
    protected function resetUrl($notifiable): string
    {
        return FrontendUrl::to(
            '/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset()),
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Промена на лозинката — Zdravje360')
            ->greeting('Здраво!')
            ->line('Добивме барање за промена на лозинката на вашата сметка.')
            ->action('Постави нова лозинка', $url)
            ->line("Линкот важи {$expire} минути.")
            ->line('Ако не сте побарале промена на лозинката, игнорирајте ја оваа порака — вашата лозинка останува иста.');
    }
}
