<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ModerationDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{label: string, count: int, url: string}>  $queues
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly int $totalPending,
        public readonly array $queues,
        public readonly string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Дневен преглед на модерација — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.moderation-digest',
        );
    }
}
