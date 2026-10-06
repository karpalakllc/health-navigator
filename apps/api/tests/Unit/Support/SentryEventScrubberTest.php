<?php

namespace Tests\Unit\Support;

use App\Support\SentryEventScrubber;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Sentry\Breadcrumb;
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

    public function test_search_terms_are_dropped_from_the_query_string_and_body(): void
    {
        // A health search ("ХИВ тест") is sensitive even without a user id.
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example/api/v1/search?q=hiv&city=skopje',
            'query_string' => 'q=hiv&city=skopje',
            'data' => ['q' => 'ХИВ тест', 'city' => 'Скопје'],
        ]);

        $request = SentryEventScrubber::beforeSend($event)?->getRequest();

        $this->assertSame(SentryEventScrubber::FILTERED, $request['data']['q']);
        $this->assertSame('Скопје', $request['data']['city']);
        $this->assertStringNotContainsString('hiv', (string) $request['query_string']);
        $this->assertStringNotContainsString('hiv', (string) $request['url']);
    }

    public function test_livewire_updates_drop_two_factor_codes_and_recovery_codes(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example/livewire/update',
            'data' => [
                'components' => [[
                    'snapshot' => json_encode([
                        'data' => ['data' => [[
                            'email' => 'jane@example.com',
                            'password' => 'correct-horse-battery',
                            'multiFactor' => [[
                                'app' => [['code' => '654321', 'recoveryCode' => 'wxyz-1234', 'useRecoveryCode' => false], ['s' => 'arr']],
                            ], ['s' => 'arr']],
                        ], ['s' => 'arr']]],
                        'memo' => ['name' => 'app.filament.pages.auth.login'],
                    ]),
                    'updates' => [
                        'data.multiFactor.app.code' => '123456',
                        'data.multiFactor.app.recoveryCode' => 'abcd-efgh',
                        'data.multiFactor.app.useRecoveryCode' => true,
                    ],
                    'calls' => [],
                ]],
            ],
        ]);

        $component = SentryEventScrubber::beforeSend($event)?->getRequest()['data']['components'][0];

        $this->assertSame(SentryEventScrubber::FILTERED, $component['updates']['data.multiFactor.app.code']);
        $this->assertSame(SentryEventScrubber::FILTERED, $component['updates']['data.multiFactor.app.recoveryCode']);
        $this->assertTrue($component['updates']['data.multiFactor.app.useRecoveryCode']);

        // The snapshot is the component's previous state, as a JSON string.
        $this->assertStringNotContainsString('654321', $component['snapshot']);
        $this->assertStringNotContainsString('wxyz-1234', $component['snapshot']);
        $this->assertStringNotContainsString('correct-horse-battery', $component['snapshot']);
        $this->assertStringNotContainsString('jane@example.com', $component['snapshot']);
        $this->assertStringContainsString('app.filament.pages.auth.login', $component['snapshot']);
    }

    public function test_mounted_action_codes_and_encrypted_arguments_are_dropped(): void
    {
        // Setting up app authentication from the profile mounts an action whose
        // form takes the code and whose arguments carry the encrypted secret.
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example/livewire/update',
            'data' => [
                'components' => [[
                    'snapshot' => json_encode([
                        'data' => [
                            'mountedActions' => [[[
                                'name' => 'setUpAppAuthentication',
                                'arguments' => [['encrypted' => 'eyJpdiI6InNldC11cC1zZWNyZXQifQ'], ['s' => 'arr']],
                                'data' => [['code' => '246810'], ['s' => 'arr']],
                            ], ['s' => 'arr']]],
                        ],
                        'memo' => ['name' => 'filament.pages.edit-profile'],
                    ]),
                    'updates' => [
                        'mountedActions.0.data.code' => '135790',
                        'mountedActions.0.data.label' => 'kept',
                    ],
                    'calls' => [],
                ]],
            ],
        ]);

        $component = SentryEventScrubber::beforeSend($event)?->getRequest()['data']['components'][0];

        $this->assertSame(SentryEventScrubber::FILTERED, $component['updates']['mountedActions.0.data.code']);
        $this->assertSame('kept', $component['updates']['mountedActions.0.data.label']);
        $this->assertStringNotContainsString('246810', $component['snapshot']);
        $this->assertStringNotContainsString('eyJpdiI6InNldC11cC1zZWNyZXQifQ', $component['snapshot']);
        $this->assertStringContainsString('setUpAppAuthentication', $component['snapshot']);
    }

    public function test_a_code_outside_two_factor_context_is_kept(): void
    {
        $event = Event::createEvent();
        $event->setRequest(['url' => 'https://api.example/api/v1/x', 'data' => ['code' => 'MK', 'encrypted' => 'opaque']]);

        $data = SentryEventScrubber::beforeSend($event)?->getRequest()['data'];

        $this->assertSame('MK', $data['code']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['encrypted']);
    }

    public function test_two_factor_keys_are_dropped_wherever_they_sit(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example/admin/profile',
            'data' => [
                'recovery_codes' => ['one', 'two'],
                'recovery_code' => 'one',
                'app_authentication_recovery_codes' => ['one'],
                'multi_factor' => ['app' => ['code' => '123456']],
                'code' => 'kept: not under a multiFactor parent',
            ],
        ]);

        $data = SentryEventScrubber::beforeSend($event)?->getRequest()['data'];

        $this->assertSame(SentryEventScrubber::FILTERED, $data['recovery_codes']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['recovery_code']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['app_authentication_recovery_codes']);
        $this->assertSame(SentryEventScrubber::FILTERED, $data['multi_factor']['app']['code']);
        $this->assertSame('kept: not under a multiFactor parent', $data['code']);
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

    private static function scrubbedMessage(string $message): string
    {
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new RuntimeException($message))]);

        return (string) SentryEventScrubber::beforeSend($event)?->getExceptions()[0]->getValue();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function harmlessText(): array
    {
        return [
            'canonical uuid' => ['Doctor 9b2f4c1e-7a3d-4e8b-9c0f-1a2b3c4d5e6f not found'],
            'uppercase uuid' => ['Job 9B2F4C1E-7A3D-4E8B-9C0F-1A2B3C4D5E6F failed'],
            '40-char commit sha' => ['release 3f786850e387550fdab836ed7e6dc881de23001b deployed'],
            'migration name' => ['Migrating: 2026_10_07_100001_verify_existing_staff_accounts_and_contest_registrations'],
            'snake_case key with digits' => ['Missing key triage_step_2_answer_option_3_label_override_v2 in payload'],
            'retina image path' => ['Unable to read /var/www/storage/app/public/x@2x.png'],
            'retina image name' => ['Missing asset logo@2x.png'],
            'windows path' => ['Unable to read C:\\srv\\x@cdn.example.org.txt'],
            'pascal case class name' => ['Unresolved App_Http_Controllers_Api_V1_AuthController_2026 binding'],
        ];
    }

    #[DataProvider('harmlessText')]
    public function test_identifiers_that_are_not_secrets_survive(string $message): void
    {
        $this->assertSame($message, self::scrubbedMessage($message));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function secretText(): array
    {
        $entropy = 'Kq3VbX9mLr2TzW8nYp4HcJ6dFs1GtAe5Ru7Mi0Oa';
        $sanctum = $entropy.hash('crc32b', $entropy);

        return [
            'sanctum token with prefix' => ['Bearer 17|z360_'.$sanctum.' rejected', $entropy],
            'sanctum token without prefix' => ['token 3|'.$sanctum, $entropy],
            'bare prefixed sanctum secret' => ['secret z360_'.$sanctum.' leaked', $entropy],
            'bcrypt hash' => ['hash $2y$12$kOBD7Q0gtjx9c85wxOWxXOcWCnR2K4svzU66W3Q9G73w0fDy.vwry', 'kOBD7Q0gtjx9c85'],
            'argon2id hash' => ['hash $argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG', 'RdescudvJCsgt3ub'],
            '64-hex reset token' => ['token '.self::TOKEN, self::TOKEN],
            'long base64url secret' => ['key 3q2-7wAbCdEfGhIjKlMnOpQrStUvWxYz0123456789+abc=', 'AbCdEfGhIjKlMnOp'],
            'email address' => ['Mail to jane.doe+x@example.co.uk failed', 'jane.doe'],
            'email after a space-free key' => ['to=jane@example.mk', 'jane@example.mk'],
            'email in a url path' => ['GET /users/john@example.com 404', 'john@example.com'],
            'email after a backslash' => ['Unknown user DOMAIN\\jane@example.org', 'jane@example.org'],
            'base64url secret split by "_"' => ['key PdEa9xQ2mK7vLr4TzW8nYpB_8H9JcF6dSs1GtAe5Ru7 rejected', '8H9JcF6dSs1GtAe5Ru7'],
            'base64url secret with two "_"' => ['key Xa9_kQ2mK7vLr4TzW8nYp-H9JcF6d_Ss1GtAe5Ru7Mi0 rejected', 'kQ2mK7vLr4TzW8nYp'],
        ];
    }

    #[DataProvider('secretText')]
    public function test_secrets_in_free_text_are_redacted(string $message, string $secret): void
    {
        $scrubbed = self::scrubbedMessage($message);

        $this->assertStringNotContainsString($secret, $scrubbed);
        $this->assertStringContainsString(SentryEventScrubber::FILTERED, $scrubbed);
    }

    public function test_breadcrumbs_are_scrubbed_through_a_cacheable_config_callable(): void
    {
        $callable = config('sentry.before_breadcrumb');

        $this->assertIsArray($callable, 'A closure would break php artisan config:cache.');
        $this->assertIsCallable($callable);

        // What sentry-laravel records for Log::warning($message, $context).
        $breadcrumb = new Breadcrumb(
            Breadcrumb::LEVEL_WARNING,
            Breadcrumb::TYPE_DEFAULT,
            'log.warning',
            'Search fallback for jane@example.com',
            [
                'message' => 'Meilisearch said: no match for '.self::TOKEN,
                'password' => 'hunter2',
                'nested' => ['query' => 'reset jane@example.com'],
                'count' => 3,
            ],
        );

        $scrubbed = $callable($breadcrumb);

        $this->assertInstanceOf(Breadcrumb::class, $scrubbed);
        $this->assertSame('Search fallback for '.SentryEventScrubber::FILTERED, $scrubbed->getMessage());
        $metadata = $scrubbed->getMetadata();
        $this->assertSame('Meilisearch said: no match for '.SentryEventScrubber::FILTERED, $metadata['message']);
        $this->assertSame(SentryEventScrubber::FILTERED, $metadata['password']);
        $this->assertSame('reset '.SentryEventScrubber::FILTERED, $metadata['nested']['query']);
        $this->assertSame(3, $metadata['count']);
    }

    public function test_message_params_are_scrubbed(): void
    {
        $event = Event::createEvent();
        $event->setMessage('Login failed for %s', ['jane@example.com'], 'Login failed for jane@example.com');

        $scrubbed = SentryEventScrubber::beforeSend($event);

        $this->assertSame([SentryEventScrubber::FILTERED], $scrubbed?->getMessageParams());
        $this->assertSame('Login failed for '.SentryEventScrubber::FILTERED, $scrubbed?->getMessageFormatted());
    }
}
