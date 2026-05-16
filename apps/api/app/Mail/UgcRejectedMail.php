<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UgcRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $contentLabel,
        public readonly string $contentTitle,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
        public readonly ?string $rejectionNote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Содржината не е објавена — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ugc-rejected',
        );
    }
}
