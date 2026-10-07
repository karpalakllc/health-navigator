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

/** W8-B: the reviewed doctor or facility replied publicly to the member's review. */
class ReviewReplyMail extends Mailable implements ShouldQueue, Unsubscribable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $profileName,
        // The linked doctor wrote it (not staff on the profile's behalf).
        public readonly bool $fromDoctor,
        public readonly string $replyExcerpt,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Одговор на вашата рецензија — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-reply',
        );
    }
}
