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

class UgcApprovedMail extends Mailable implements ShouldQueue, Unsubscribable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $contentLabel,
        public readonly string $contentTitle,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
        // $contentLabel is a masculine noun (одговор): the template agrees with it.
        public readonly bool $masculine = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Вашата содржина е објавена — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ugc-approved',
        );
    }
}
