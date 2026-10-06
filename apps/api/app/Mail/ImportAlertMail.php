<?php

namespace App\Mail;

use App\Support\DataOps\ImportAlerter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A failed or unusually large source import (App\Support\DataOps\ImportAlerter).
 * Sent synchronously, not queued. Counts and a short error line only.
 */
class ImportAlertMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, int>  $counts
     */
    public function __construct(
        public readonly string $sourceLabel,
        public readonly string $kind,
        public readonly array $counts,
        public readonly ?string $error,
        public readonly ?string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $what = $this->kind === ImportAlerter::KIND_FAILED ? 'Неуспешен увоз' : 'Голема промена при увоз';

        return new Envelope(
            subject: "{$what}: {$this->sourceLabel} — Zdravje360",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.import-alert',
            with: [
                'failed' => $this->kind === ImportAlerter::KIND_FAILED,
                'rows' => $this->rows(),
            ],
        );
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function rows(): array
    {
        $labels = [
            'seen' => 'Прочитани записи',
            'created' => 'Нови профили',
            'updated' => 'Изменети профили',
            'missing' => 'Ги нема во изворот',
            'conflicts' => 'Конфликти за преглед',
            'unmatched' => 'Неповрзани записи',
        ];

        $rows = [];

        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $this->counts)) {
                $rows[] = ['label' => $label, 'count' => (int) $this->counts[$key]];
            }
        }

        return $rows;
    }
}
