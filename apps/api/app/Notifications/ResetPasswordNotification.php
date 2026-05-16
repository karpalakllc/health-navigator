<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
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
            ->subject('Ресетирајте ја лозинката — Zdravje360')
            ->greeting('Здраво!')
            ->line('Добивме барање за ресетирање на лозинката за вашата сметка.')
            ->action('Постави нова лозинка', $url)
            ->line("Линкот истекува за {$expire} минути.")
            ->line('Ако не сте побарале ресетирање, игнорирајте ја оваа порака.');
    }
}
