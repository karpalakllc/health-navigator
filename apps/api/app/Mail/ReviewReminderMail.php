<?php

namespace App\Mail;

use App\Mail\Concerns\HasUnsubscribeLink;
use App\Mail\Concerns\Unsubscribable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * W8-B: the one reminder the member asked for on a profile („Потсети ме за
 * 2 недели“). No incentive of any kind: reviews are never bought.
 */
class ReviewReminderMail extends Mailable implements ShouldQueue, Unsubscribable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $profileName,
        public readonly bool $isDoctor,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Потсетник: споделете го искуството — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-reminder',
        );
    }
}
