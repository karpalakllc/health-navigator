<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a linked doctor how staff decided their request to change sensitive
 * profile fields: applied, or not with the reason. Never names the staff
 * member.
 */
class DoctorChangeRequestDecidedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $fieldLabels  the requested fields, in Macedonian
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly string $doctorName,
        public readonly bool $approved,
        public readonly ?string $reason,
        public readonly array $fieldLabels,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->approved
                ? 'Промените на профилот се прифатени — Zdravje360'
                : 'Промените на профилот не се прифатени — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.doctor-change-request-decided',
        );
    }
}
