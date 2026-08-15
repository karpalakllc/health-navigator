<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\VerificationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Catches translations that are *missing* rather than wrong.
 *
 * A native review can only read the strings that exist. When a whole group is
 * absent, Laravel silently falls back to English while still substituting the
 * translated :attribute, so the user gets a half-Macedonian sentence — "The
 * лозинка field must contain at least one number." — and nothing in the review
 * process ever sees it, because there is no string to review.
 *
 * Asserting that a rendered Macedonian message contains no Latin letters is a
 * cheap way to detect the fallback itself.
 */
class TranslationCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every rule Password::defaults() can enforce. These are the ones that were
     * missing; the point of listing all five is that adding a rule to the
     * defaults without a translation now fails here.
     */
    public function test_password_rule_messages_are_fully_translated(): void
    {
        $this->app->setLocale('mk');

        $validator = Validator::make(
            ['password' => 'aaaaaaaaaa'],
            ['password' => Password::min(10)->letters()->numbers()->mixedCase()->symbols()],
        );

        $this->assertTrue($validator->fails());

        foreach ($validator->errors()->all() as $message) {
            $this->assertUntranslatedFallbackAbsent($message);
        }
    }

    public function test_the_uncompromised_message_is_translated(): void
    {
        $this->app->setLocale('mk');

        // Rendered separately: it is the one message that needs a network-backed
        // rule to trigger, so it is the easiest of the five to leave behind.
        $validator = Validator::make(
            ['password' => 'password123456'],
            ['password' => Password::min(8)->uncompromised()],
        );

        $this->assertTrue($validator->fails());

        foreach ($validator->errors()->all() as $message) {
            $this->assertUntranslatedFallbackAbsent($message);
        }
    }

    private function assertUntranslatedFallbackAbsent(string $message): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/[A-Za-z]/',
            $message,
            "Macedonian message fell back to English: [{$message}]",
        );
    }

    /**
     * The hourly ceiling had no coverage — only the 60-second cooldown did, and
     * a test that sends twice passes whether the ceiling is 6 or absent.
     */
    public function test_the_hourly_mail_ceiling_stops_the_seventh_message(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        $sent = 0;

        // Six are allowed per hour; the cooldown is what spaces them out, so step
        // past it each time rather than sleeping.
        for ($i = 0; $i < 8; $i++) {
            if (VerificationMailer::sendVerificationLink($user->fresh())) {
                $sent++;
            }

            $this->travel(61)->seconds();
        }

        $this->assertSame(6, $sent, 'The hourly ceiling did not cap sends at 6.');
    }

    public function test_the_ceiling_releases_after_an_hour(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 8; $i++) {
            VerificationMailer::sendVerificationLink($user->fresh());
            $this->travel(61)->seconds();
        }

        $this->assertFalse(VerificationMailer::sendVerificationLink($user->fresh()));

        $this->travel(61)->minutes();

        $this->assertTrue(
            VerificationMailer::sendVerificationLink($user->fresh()),
            'The address stayed locked out after the hour elapsed.',
        );
    }
}
