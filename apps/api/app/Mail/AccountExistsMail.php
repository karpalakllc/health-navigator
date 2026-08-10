<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when someone submits the registration form with an address that already
 * has an account.
 *
 * This is what makes registration non-enumerable: the API returns exactly the
 * same response either way, and the only thing that differs is which message
 * the real owner of the address receives. It also happens to be the more useful
 * behaviour — someone who forgot they had an account gets a way back in.
 */
class AccountExistsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $loginUrl,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Обид за регистрација со вашата е-адреса — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-exists',
        );
    }
}
