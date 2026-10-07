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
 * W8-B: the opt-in monthly „Ова се промените на кои придонесовте“. Counts
 * about the member's own published content only (App\Support\Notifications\ImpactStats).
 */
class ImpactDigestMail extends Mailable implements ShouldQueue, Unsubscribable
{
    use HasUnsubscribeLink, Queueable, SerializesModels;

    /**
     * @param  array{review_views: int, helpful_votes: int, replies: int, forum_answers: int, forum_replies_received: int, forum_helpful_votes: int, review_views_total: int}  $stats
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly string $monthLabel,
        public readonly array $stats,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ова се промените на кои придонесовте — '.$this->monthLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.impact-digest',
        );
    }
}
