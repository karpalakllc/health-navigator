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
 * W8-B: new „Корисно“ votes on the member's review, batched (at most one a
 * day per review, reviews:notify-helpful). Counts only — never who voted.
 */
class ReviewHelpfulMail extends Mailable implements ShouldQueue, Unsubscribable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $profileName,
        public readonly int $newVotes,
        public readonly int $totalVotes,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Вашата рецензија им помогна на други — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-helpful',
        );
    }
}
