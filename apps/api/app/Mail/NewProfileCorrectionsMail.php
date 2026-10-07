<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert: profile corrections and objections that arrived since the
 * last run of corrections:alert-staff. Counts only — no requester, contact,
 * message or profile — so the mail carries nothing personal.
 */
class NewProfileCorrectionsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{label: string, count: int, days: int}>  $types
     */
    public function __construct(
        public readonly int $newRequests,
        public readonly int $openRequests,
        public readonly int $overdueRequests,
        public readonly array $types,
        public readonly string $queueUrl,
        /** Profiles at or over the profile-report priority threshold. */
        public readonly int $priorityProfiles = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Нови барања за профили — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-profile-corrections',
        );
    }
}
