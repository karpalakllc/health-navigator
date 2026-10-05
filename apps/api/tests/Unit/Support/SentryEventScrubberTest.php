<?php

namespace Tests\Unit\Support;

use App\Support\SentryEventScrubber;
use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Tests\TestCase;

class SentryEventScrubberTest extends TestCase
{
    private const TOKEN = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

    public function test_request_body_drops_email_variants_and_triage_answers(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example/api/v1/triage',
            'data' => [
                'new_email' => 'jane@example.com',
                'email_confirmation' => 'jane@example.com',
                'answers' => [['step_key' => 'symptom', 'values' => ['chest_pain']]],
                'nested' => ['values' => ['pregnant']],
                'name' => 'kept',
            ],
        ]);

        $data = SentryEventScrubber::beforeSend($event)?->getRequest()['data'];

        $this->assertSame(SentryEventScrubber::FILTERED, $data['new_email']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['email_confirmation']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['answers']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['nested']['values']);
        $this->assertSame('kept', $data['name']);
    }

    public function test_query_exception_message_keeps_the_sql_but_not_its_bindings(): void
    {
        $exception = new QueryException(
            'sqlite',
            'select * from "users" where "email" = ? and "remember_token" = ?',
            ['jane@example.com', 'secret-remember-value'],
            new PDOException('SQLSTATE[HY000]: General error'),
        );
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($exception)]);

        $value = SentryEventScrubber::beforeSend($event, EventHint::fromArray(['exception' => $exception]))
            ?->getExceptions()[0]->getValue();

        $this->assertStringContainsString('where "email" = ? and "remember_token" = ?', (string) $value);
        $this->assertStringContainsString('SQLSTATE[HY000]', (string) $value);
        $this->assertStringNotContainsString('jane@example.com', (string) $value);
        $this->assertStringNotContainsString('secret-remember-value', (string) $value);

        // Without the hint the bindings cannot be told apart, so the SQL goes.
        $event->setExceptions([new ExceptionDataBag($exception)]);
        $value = SentryEventScrubber::beforeSend($event)?->getExceptions()[0]->getValue();

        $this->assertSame('SQLSTATE[HY000]: General error (Connection: sqlite, SQL: [Filtered])', $value);
    }

    public function test_exception_messages_redact_emails_and_long_tokens_without_a_hint(): void
    {
        $exception = new RuntimeException(
            "Duplicate entry 'jane.doe+x@example.co.uk' for token ".self::TOKEN.' and 1|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcd',
        );
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag($exception)]);
        $event->setMessage('Mail to jane@example.com failed');

        $scrubbed = SentryEventScrubber::beforeSend($event);
        $value = (string) $scrubbed?->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString('jane.doe', $value);
        $this->assertStringNotContainsString(self::TOKEN, $value);
        $this->assertStringNotContainsString('AbCdEfGhIjKlMnOpQrStUvWxYz', $value);
        $this->assertStringContainsString('Duplicate entry', $value);
        $this->assertStringNotContainsString('jane@example.com', (string) $scrubbed?->getMessage());
    }

    public function test_short_identifiers_and_paths_are_left_alone(): void
    {
        $message = 'No query results for model [App\Models\Doctor] 42 at /var/www/apps/api/app/Http/Controllers/DoctorController.php';
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new RuntimeException($message))]);

        $this->assertSame($message, SentryEventScrubber::beforeSend($event)?->getExceptions()[0]->getValue());
    }

    public function test_transactions_are_scrubbed_through_a_cacheable_config_callable(): void
    {
        $callable = config('sentry.before_send_transaction');

        $this->assertIsArray($callable, 'A closure would break php artisan config:cache.');
        $this->assertIsCallable($callable);

        $transaction = Event::createTransaction();
        $transaction->setRequest([
            'url' => 'https://api.example/api/v1/auth/reset-password?token='.self::TOKEN.'&email=jane@example.com',
            'data' => ['password' => 'hunter2'],
        ]);

        $request = $callable($transaction)?->getRequest();

        $this->assertStringNotContainsString(self::TOKEN, $request['url']);
        $this->assertStringNotContainsString('jane', $request['url']);
        $this->assertSame(SentryEventScrubber::FILTERED, $request['data']['password']);
    }
}
