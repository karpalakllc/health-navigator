<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert: reports that arrived since the last run of reports:alert-staff.
 * Counts only — no reporter, no note, no reported text — so the mail carries
 * nothing personal; the queue in the admin panel has the rest.
 */
class NewContentReportsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{label: string, count: int}>  $reasons
     */
    public function __construct(
        public readonly int $newReports,
        public readonly int $openReports,
        public readonly array $reasons,
        public readonly string $queueUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Нови пријави за содржина — Zdravje360',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-content-reports',
        );
    }
}
