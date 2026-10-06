<?php

namespace Tests\Feature\Api\V1;

use App\Actions\ChangeUsername;
use App\Enums\ForumContentStatus;
use App\Enums\ReportReason;
use App\Models\AnalyticsEvent;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ReviewHelpfulVotes;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * D5: a member can download a JSON copy of their own data — profile, reviews,
 * forum topics and replies, consents with their timestamps — and nothing that
 * belongs to anyone else.
 */
class AccountExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
    }

    /**
     * @return array<string, mixed>
     */
    private function download(User $user): array
    {
        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/me/export')->assertOk();

        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.json', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        return json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_export_holds_the_members_own_data_with_consent_times(): void
    {
        $member = User::factory()->create([
            'name' => 'Марија Костовска',
            'display_name' => 'Марија К.',
            'username' => 'marija_k',
            'email' => 'marija@example.com',
        ]);
        $termsAcceptedAt = now()->subWeek()->startOfSecond();
        $member->forceFill(['terms_accepted_at' => $termsAcceptedAt, 'terms_version' => '2026-10-06'])->save();
        app(ChangeUsername::class)->handle($member, 'marija_bt');
        $member->createToken('Firefox · Linux');
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);

        Review::factory()->approved()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'rating' => 4,
            'body' => 'Внимателна и јасна.',
        ]);
        Review::factory()->rejected()->create([
            'user_id' => $member->id,
            'rating' => 1,
            'rejection_note' => 'Лични податоци во текстот.',
        ]);

        $acceptedAt = now()->subDays(3)->startOfSecond();
        $topic = ForumTopic::factory()->create([
            'user_id' => $member->id,
            'title' => 'Прашање за вакцини',
            'community_rules_accepted_at' => $acceptedAt,
        ]);
        ForumTopic::factory()->pending()->create(['user_id' => $member->id, 'title' => 'Мое на чекање']);
        ForumPost::factory()->create(['user_id' => $member->id, 'forum_topic_id' => $topic->id, 'body' => 'Мој одговор']);

        AnalyticsEvent::query()->create(['event' => 'user.login', 'user_id' => $member->id, 'occurred_at' => now()]);

        $export = $this->download($member);

        $this->assertSame('zdravje360.account-export', $export['format']);
        $this->assertSame(1, $export['version']);
        $this->assertSame('Марија Костовска', $export['profile']['name']);
        $this->assertSame('Марија К.', $export['profile']['display_name']);
        $this->assertSame('marija_bt', $export['profile']['username']);
        $this->assertNotNull($export['profile']['username_changed_at']);
        $this->assertSame('marija_k', $export['profile']['previous_usernames'][0]['username']);
        $this->assertSame('changed', $export['profile']['previous_usernames'][0]['reason']);
        $this->assertSame($termsAcceptedAt->toIso8601String(), $export['profile']['terms_accepted_at']);
        $this->assertSame('2026-10-06', $export['profile']['terms_version']);
        $this->assertSame('marija@example.com', $export['profile']['email']);
        $this->assertSame(['Member'], $export['profile']['roles']);

        $this->assertCount(2, $export['reviews']);
        $approved = collect($export['reviews'])->firstWhere('status', 'approved');
        $this->assertSame(['kind' => 'doctor', 'name' => $doctor->full_name, 'slug' => 'ana-petrovska'], $approved['about']);
        $this->assertSame('Внимателна и јасна.', $approved['body']);
        $this->assertSame('Лични податоци во текстот.', collect($export['reviews'])->firstWhere('status', 'rejected')['rejection_note']);

        $this->assertEqualsCanonicalizing(['Прашање за вакцини', 'Мое на чекање'], array_column($export['forum_topics'], 'title'));
        $this->assertSame('Мој одговор', $export['forum_posts'][0]['body']);
        $this->assertSame('Прашање за вакцини', $export['forum_posts'][0]['topic_title']);

        $this->assertCount(1, $export['consents']);
        $this->assertSame('forum_community_rules', $export['consents'][0]['type']);
        $this->assertSame($topic->id, $export['consents'][0]['forum_topic_id']);
        $this->assertSame($acceptedAt->toIso8601String(), $export['consents'][0]['accepted_at']);

        $this->assertSame('Firefox · Linux', $export['devices'][0]['name']);
        $this->assertSame('user.login', $export['activity'][0]['event']);
    }

    public function test_the_export_holds_aspects_removals_and_resubmissions(): void
    {
        $member = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $review = Review::factory()->rejected()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);
        $review->aspectRatings()->create(['aspect' => 'communication', 'rating' => 5]);
        $review->aspectRatings()->create(['aspect' => 'waiting_time', 'rating' => 2]);
        $review->forceFill(['resubmission_count' => 1, 'resubmitted_at' => now()->subDay()])->save();

        $removed = Review::factory()->rejected()->create([
            'user_id' => $member->id,
            'removed_at' => now()->subDays(2),
            'removal_category' => 'spam',
        ]);
        $topic = ForumTopic::factory()->create([
            'user_id' => $member->id,
            'status' => ForumContentStatus::Rejected,
            'removed_at' => now()->subDays(3),
            'removal_category' => 'abuse',
        ]);
        ForumPost::factory()->create([
            'user_id' => $member->id,
            'forum_topic_id' => $topic->id,
            'status' => ForumContentStatus::Rejected,
            'removed_at' => now()->subDays(3),
            'removal_category' => 'abuse',
        ]);

        $export = $this->download($member);

        $resent = collect($export['reviews'])->firstWhere('id', $review->id);
        $this->assertSame(['communication' => 5, 'waiting_time' => 2], $resent['aspects']);
        $this->assertSame(1, $resent['resubmission_count']);
        $this->assertNotNull($resent['resubmitted_at']);
        $this->assertNull($resent['removed_at']);

        $takenDown = collect($export['reviews'])->firstWhere('id', $removed->id);
        $this->assertSame('spam', $takenDown['removal_category']);
        $this->assertNotNull($takenDown['removed_at']);

        $this->assertSame('abuse', $export['forum_topics'][0]['removal_category']);
        $this->assertNotNull($export['forum_topics'][0]['removed_at']);
        $this->assertSame('abuse', $export['forum_posts'][0]['removal_category']);
        $this->assertNotNull($export['forum_posts'][0]['removed_at']);
    }

    public function test_the_export_never_includes_another_members_data(): void
    {
        $member = User::factory()->create(['email' => 'mine@example.com']);
        $other = User::factory()->create([
            'name' => 'Друг Корисник',
            'display_name' => 'Друг К.',
            'username' => 'tugjinec',
            'email' => 'other@example.com',
        ]);
        app(ChangeUsername::class)->handle($other, 'tugjinec_nov');
        $moderator = User::factory()->moderator()->create(['name' => 'Модератор Тим', 'email' => 'mod@example.com']);

        // Another member's topic that the member replied to, later taken down.
        $othersTopic = ForumTopic::factory()->create([
            'user_id' => $other->id,
            'title' => 'Туѓа тема што е симната',
            'status' => ForumContentStatus::Rejected,
            'moderated_by_id' => $moderator->id,
        ]);
        ForumPost::factory()->create(['user_id' => $member->id, 'forum_topic_id' => $othersTopic->id, 'moderated_by_id' => $moderator->id]);
        ForumPost::factory()->create(['user_id' => $other->id, 'forum_topic_id' => $othersTopic->id, 'body' => 'Туѓ одговор']);
        Review::factory()->approved()->create(['user_id' => $other->id, 'body' => 'Туѓа рецензија']);
        $other->createToken('Туѓ уред');
        AnalyticsEvent::query()->create(['event' => 'user.login', 'user_id' => $other->id, 'occurred_at' => now()]);

        $raw = json_encode($this->download($member), JSON_UNESCAPED_UNICODE);

        foreach (['Друг', 'tugjinec', 'other@example.com', 'Туѓа тема', 'Туѓ одговор', 'Туѓа рецензија', 'Туѓ уред', 'Модератор', 'mod@example.com'] as $foreign) {
            $this->assertStringNotContainsString($foreign, (string) $raw);
        }

        $export = json_decode((string) $raw, true);
        $this->assertCount(1, $export['forum_posts']);
        $this->assertNull($export['forum_posts'][0]['topic_title']);
        $this->assertSame([], $export['reviews']);
        $this->assertSame([], $export['activity']);
    }

    public function test_the_export_includes_the_members_reports_and_helpful_votes_only(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $review = Review::factory()->approved()->create(['user_id' => $other->id]);
        $topic = ForumTopic::factory()->create(['user_id' => $other->id]);

        ContentReport::factory()->about($review)->create([
            'user_id' => $member->id,
            'reason' => ReportReason::PersonalData,
            'note' => 'Го наведува моето име.',
        ]);
        ContentReport::factory()->about($topic)->create(['user_id' => $other->id, 'note' => 'Туѓа белешка']);
        ReviewHelpfulVotes::add($review, $member);
        ReviewHelpfulVotes::add(Review::factory()->approved()->create(), $other);

        $export = $this->download($member);

        $this->assertCount(1, $export['content_reports']);
        $this->assertSame(['kind' => 'review', 'id' => $review->id], $export['content_reports'][0]['about']);
        $this->assertSame('personal_data', $export['content_reports'][0]['reason']);
        $this->assertSame('Го наведува моето име.', $export['content_reports'][0]['note']);
        $this->assertSame('open', $export['content_reports'][0]['status']);
        $this->assertArrayNotHasKey('resolved_by_id', $export['content_reports'][0]);

        $this->assertCount(1, $export['helpful_votes']);
        $this->assertSame($review->id, $export['helpful_votes'][0]['review_id']);
        $this->assertNotNull($export['helpful_votes'][0]['voted_at']);
        $this->assertStringNotContainsString('Туѓа белешка', (string) json_encode($export, JSON_UNESCAPED_UNICODE));
    }

    public function test_the_export_is_rate_limited_per_account(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        for ($i = 0; $i < 5; $i++) {
            $this->get('/api/v1/me/export')->assertOk();
        }

        $this->getJson('/api/v1/me/export')
            ->assertStatus(429)
            ->assertJsonPath('code', 'account.export_throttled')
            ->assertHeader('Retry-After');

        // Another member has their own budget.
        Sanctum::actingAs(User::factory()->create());
        $this->get('/api/v1/me/export')->assertOk();
    }

    public function test_the_export_requires_a_session(): void
    {
        $this->getJson('/api/v1/me/export')->assertUnauthorized();
    }
}
