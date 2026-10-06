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
        // $contentLabel is a masculine noun (одговор): the template agrees with it.
        public readonly bool $masculine = false,
        // Taken down after a report, i.e. it had been public: „отстранет(а)“,
        // not „не беше објавен(а)“.
        public readonly bool $removed = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->removed
                ? 'Содржината е отстранета — Zdravje360'
                : 'Содржината не е објавена — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ugc-rejected',
        );
    }
}
