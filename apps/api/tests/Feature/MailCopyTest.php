<?php

namespace Tests\Feature;

use App\Mail\ModerationDigestMail;
use App\Mail\UgcApprovedMail;
use App\Mail\UgcRejectedMail;
use App\Mail\UgcSubmittedMail;
use App\Mail\WelcomeMail;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Wording of the Macedonian mail, read as a whole message: the framework
 * wrapper around it and the sentences assembled from parts.
 */
class MailCopyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The wrapper used to stay English ("Regards", the trouble-clicking line,
     * "All rights reserved") under Macedonian body text. Rendered under the
     * English locale on purpose: queue workers never see the request locale.
     */
    public function test_the_notification_wrapper_is_macedonian_whatever_the_locale(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();

        $html = (string) (new ResetPasswordNotification('token'))->toMail($user)->render();

        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertStringContainsString('Поздрав,', $html);
        $this->assertStringContainsString('Ако копчето „Постави нова лозинка“ не работи', $html);
        $this->assertStringContainsString('Сите права задржани.', $html);
    }

    public function test_the_welcome_mail_footer_and_disclaimer_read_correctly(): void
    {
        $this->app->setLocale('en');

        $html = (new WelcomeMail('Ана', 'https://example.test/login'))->render();

        $this->assertStringContainsString('Сите права задржани.', $html);
        // Was „не ја заменува совет“: feminine clitic on a masculine noun.
        $this->assertStringContainsString('не го заменува советот од лиценциран здравствен работник', $html);
    }

    /**
     * The approval/rejection/receipt mails said „Вашата одговор … е одобрена“ for
     * a forum reply: the sentence was written for the feminine nouns (тема,
     * рецензија) and the masculine одговор was slotted into it.
     */
    public function test_a_forum_reply_is_referred_to_in_the_masculine(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $member = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'sleep',
            'title' => 'Сон',
        ]);
        $post = ForumPost::factory()->pending()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $member->id,
        ]);

        $post->approve($moderator);

        Mail::assertQueued(UgcApprovedMail::class, function (UgcApprovedMail $mail): bool {
            $text = self::text($mail->render());

            return str_contains($text, 'Вашиот одговор во темата „Сон“ е одобрен')
                && ! str_contains($text, 'Вашата одговор');
        });
    }

    public function test_feminine_and_masculine_receipts_and_rejections_agree(): void
    {
        $this->app->setLocale('en');

        $review = self::text((new UgcSubmittedMail('Ана', 'рецензија за', 'д-р Ана', 'https://x.test', 'Мои рецензии'))->render());
        $this->assertStringContainsString('вашата рецензија за „д-р Ана“ е примена', $review);
        $this->assertStringContainsString('кога ќе биде објавена', $review);

        $reply = self::text((new UgcRejectedMail('Ана', 'одговор во темата', 'Сон', 'https://x.test', 'Мој форум', null, true))->render());
        $this->assertStringContainsString('вашиот одговор во темата „Сон“ не беше објавен ', $reply);

        $removed = self::text((new UgcRejectedMail('Ана', 'одговор во темата', 'Сон', 'https://x.test', 'Мој форум', 'Навреда.', true, removed: true))->render());
        $this->assertStringContainsString('Вашиот одговор во темата „Сон“ е отстранет од Zdravje360 по пријава', $removed);
        $this->assertStringContainsString('Причина: Навреда.', $removed);
        $this->assertStringNotContainsString('не беше објавен', $removed);
    }

    public function test_the_digest_counts_one_item_in_the_singular(): void
    {
        $text = self::text((new ModerationDigestMail('Ана', 1, [
            ['label' => 'Рецензии', 'count' => 1, 'url' => 'https://x.test/reviews'],
        ], 'https://x.test/admin'))->render());

        $this->assertStringContainsString('Имате 1 ставка што чека модерација', $text);
        $this->assertStringNotContainsString('ставки што чекаат', $text);
    }

    /**
     * The brand link in the header pointed at APP_URL, the API host, whose
     * root redirects to the admin sign-in.
     */
    public function test_the_header_brand_links_to_the_public_site(): void
    {
        config(['app.url' => 'https://api.example.test', 'zdravje.frontend_url' => 'https://www.example.test']);

        $mail = new WelcomeMail('Ана', 'https://www.example.test/login');
        $html = $mail->render();

        $this->assertStringContainsString('href="https://www.example.test"', $html);
        $this->assertStringNotContainsString('https://api.example.test', $html);

        $text = (string) app(Markdown::class)->renderText((string) $mail->content()->markdown, $mail->buildViewData());
        $this->assertStringContainsString('https://www.example.test', $text);
        $this->assertStringNotContainsString('https://api.example.test', $text);
    }

    /** The rendered mail as plain text: no tags, whitespace collapsed. */
    private static function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
    }
}
