<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member how their report ended (docs/notice-and-action.md): the
 * content was kept or removed. Neutral, and never names the moderator.
 */
class ContentReportOutcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        // „рецензијата за“, „темата“, „одговорот во темата“: definite, as the sentence needs.
        public readonly string $contentLabel,
        public readonly string $contentTitle,
        public readonly bool $removed,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Вашата пријава е прегледана — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.content-report-outcome',
        );
    }
}
