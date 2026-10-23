<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Enums\NotificationType;
use App\Enums\UserKind;
use App\Mail\ForumReplyMail;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicSubscription;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Forum\ForumProfileLinks;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Forum upgrades (docs/forum.md): following a topic, „Најкорисен одговор“
 * and profile cards for pasted links — each behind its admin switch.
 */
class ForumUpgradesTest extends TestCase
{
    use RefreshDatabase;

    private ForumCategory $category;

    private User $opener;

    private ForumTopic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['zdravje.frontend_url' => 'https://zdravje360.test']);
        Mail::fake();

        $this->category = ForumCategory::factory()->create(['slug' => 'general']);
        $this->opener = User::factory()->create();
        $this->topic = ForumTopic::factory()->create([
            'forum_category_id' => $this->category->id,
            'slug' => 'water',
            'title' => 'Колку вода дневно?',
            'user_id' => $this->opener->id,
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return '/api/v1/forum/categories/general/topics/water'.$suffix;
    }

    private function reply(User $author, string $body = 'Јас пијам околу два литри дневно.'): void
    {
        Sanctum::actingAs($author);
        $this->postJson($this->url('/posts'), ['body' => $body])->assertCreated();
    }

    private function setting(string $flag, bool $on): void
    {
        SiteSetting::current()->update([$flag => $on]);
    }

    // ── Following ───────────────────────────────────────────────────────

    public function test_a_member_follows_and_unfollows_a_topic(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.topic.viewer.is_following', false);
        $this->putJson($this->url('/follow'))->assertOk()->assertJsonPath('data.following', true);
        $this->putJson($this->url('/follow'))->assertOk();
        $this->assertSame(1, ForumTopicSubscription::query()->where('user_id', $member->id)->count());
        $this->getJson($this->url())->assertJsonPath('data.topic.viewer.is_following', true);

        $this->getJson('/api/v1/me/forum/following')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'water')
            ->assertJsonPath('data.0.category.slug', 'general');

        $this->deleteJson($this->url('/follow'))->assertOk()->assertJsonPath('data.following', false);
        $this->assertFalse(ForumTopicSubscription::isFollowing($member, $this->topic));
    }

    public function test_following_needs_a_signed_in_verified_member(): void
    {
        $this->putJson($this->url('/follow'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->unverified()->create());
        $this->putJson($this->url('/follow'))->assertForbidden();
    }

    public function test_a_pending_topic_cannot_be_followed(): void
    {
        ForumTopic::factory()->pending()->create(['forum_category_id' => $this->category->id, 'slug' => 'pending']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/forum/categories/general/topics/pending/follow')->assertNotFound();
    }

    public function test_replying_follows_the_topic_and_followers_are_told_once_per_unread_line(): void
    {
        $follower = User::factory()->create();
        ForumTopicSubscription::follow($follower, $this->topic);
        $replier = User::factory()->create();

        SiteSetting::current()->update(['forum_posts_require_moderation' => false]);

        $this->reply($replier);
        $this->assertTrue(ForumTopicSubscription::isFollowing($replier, $this->topic));

        $line = MemberNotification::query()->where('user_id', $follower->id)->sole();
        $this->assertSame(NotificationType::ForumReply, $line->type);
        $this->assertSame('Колку вода дневно?', $line->data['title']);
        $this->assertSame(1, $line->data['count']);
        $this->assertStringStartsWith('/forum/general/water#post-', $line->data['path']);
        // Never who replied.
        $this->assertArrayNotHasKey('author_name', $line->data);
        Mail::assertQueued(ForumReplyMail::class, fn (ForumReplyMail $mail): bool => $mail->hasTo($follower->email));
        // The replier is not told about their own reply.
        $this->assertSame(0, MemberNotification::query()->where('user_id', $replier->id)->count());

        // A second reply raises the count on the unread line, without an e-mail.
        $this->reply(User::factory()->create());
        $this->assertSame(1, MemberNotification::query()->where('user_id', $follower->id)->count());
        $this->assertSame(2, $line->fresh()->data['count']);
        Mail::assertQueued(ForumReplyMail::class, fn (ForumReplyMail $mail): bool => $mail->hasTo($follower->email));
        $this->assertCount(1, Mail::queued(ForumReplyMail::class, fn (ForumReplyMail $mail): bool => $mail->hasTo($follower->email)));

        // Opening the topic reads the line; the next reply starts a new one.
        Sanctum::actingAs($follower);
        $this->getJson($this->url())->assertOk();
        $this->assertNotNull($line->fresh()->read_at);

        $this->reply(User::factory()->create());
        $this->assertSame(2, MemberNotification::query()->where('user_id', $follower->id)->count());
    }

    public function test_the_topic_author_follows_their_new_topic(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'Нова тема за спиење',
            'body' => 'Колку часа спиете обично во текот на неделата?',
            'accepted_community_rules' => true,
        ])->assertCreated();

        $topic = ForumTopic::query()->where('title', 'Нова тема за спиење')->sole();
        $this->assertTrue(ForumTopicSubscription::isFollowing($member, $topic));
    }

    public function test_a_pending_reply_notifies_only_once_a_moderator_approves_it(): void
    {
        $follower = User::factory()->create();
        ForumTopicSubscription::follow($follower, $this->topic);
        SiteSetting::current()->update(['forum_posts_require_moderation' => true]);

        $this->reply(User::factory()->create());
        $this->assertSame(0, MemberNotification::query()->where('user_id', $follower->id)->count());

        $moderator = User::factory()->create(['user_kind' => UserKind::Staff]);
        $moderator->assignRole('Moderator');
        ForumPost::query()->latest('id')->firstOrFail()->approve($moderator);

        $this->assertSame(1, MemberNotification::query()->where('user_id', $follower->id)->where('type', 'forum_reply')->count());
    }

    public function test_the_forum_reply_email_respects_the_member_switch(): void
    {
        $follower = User::factory()->create();
        ForumTopicSubscription::follow($follower, $this->topic);
        NotificationPreference::for($follower)->fill(['forum_reply' => false])->save();
        SiteSetting::current()->update(['forum_posts_require_moderation' => false]);

        $this->reply(User::factory()->create());

        $this->assertSame(1, MemberNotification::query()->where('user_id', $follower->id)->count());
        Mail::assertNotQueued(ForumReplyMail::class);

        Sanctum::actingAs($follower);
        $this->getJson('/api/v1/me/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.types.forum_reply', false);
    }

    public function test_switching_following_off_hides_it_everywhere(): void
    {
        $follower = User::factory()->create();
        ForumTopicSubscription::follow($follower, $this->topic);
        $this->setting('forum_follow_enabled', false);
        SiteSetting::current()->update(['forum_posts_require_moderation' => false]);

        $this->reply(User::factory()->create());
        $this->assertSame(0, MemberNotification::query()->where('user_id', $follower->id)->count());

        Sanctum::actingAs($follower);
        $this->putJson($this->url('/follow'))->assertNotFound();
        $this->getJson($this->url())->assertOk()->assertJsonMissingPath('data.topic.viewer.is_following');
        $this->getJson('/api/v1/settings/public')->assertJsonPath('data.forum_follow_enabled', false);
    }

    // ── „Најкорисен одговор“ ────────────────────────────────────────────

    public function test_the_topic_author_marks_the_most_helpful_reply(): void
    {
        $helper = User::factory()->create(['username' => 'pomosnik']);
        $post = ForumPost::factory()->create([
            'forum_topic_id' => $this->topic->id,
            'user_id' => $helper->id,
            'body' => 'Околу 30 ml на килограм тежина е добар ориентир.',
        ]);

        Sanctum::actingAs($this->opener);
        $this->getJson($this->url())->assertJsonPath('data.topic.viewer.can_accept', true);
        $this->putJson($this->url('/accepted-answer'), ['post_id' => $post->id])->assertOk();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.topic.accepted_answer.id', $post->id)
            ->assertJsonPath('data.topic.accepted_answer.author_name', 'pomosnik')
            ->assertJsonPath('data.topic.accepted_answer.path', '/forum/general/water#post-'.$post->id)
            ->assertJsonPath('data.posts.0.is_accepted', true);

        $this->getJson('/api/v1/forum/categories/general/topics')
            ->assertJsonPath('data.0.has_accepted_answer', true);

        $this->deleteJson($this->url('/accepted-answer'))->assertOk();
        $this->assertNull($this->topic->fresh()->accepted_post_id);
    }

    public function test_others_cannot_mark_an_answer_but_a_moderator_can(): void
    {
        $post = ForumPost::factory()->create(['forum_topic_id' => $this->topic->id]);

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->getJson($this->url())->assertJsonMissingPath('data.topic.viewer.can_accept');
        $this->putJson($this->url('/accepted-answer'), ['post_id' => $post->id])->assertForbidden();

        $moderator = User::factory()->create(['user_kind' => UserKind::Staff]);
        $moderator->assignRole('Moderator');
        Sanctum::actingAs($moderator);
        $this->putJson($this->url('/accepted-answer'), ['post_id' => $post->id])->assertOk();
    }

    public function test_only_a_published_reply_by_someone_else_can_be_the_answer(): void
    {
        $own = ForumPost::factory()->create(['forum_topic_id' => $this->topic->id, 'user_id' => $this->opener->id]);
        $pending = ForumPost::factory()->pending()->create(['forum_topic_id' => $this->topic->id]);
        $elsewhere = ForumPost::factory()->create([
            'forum_topic_id' => ForumTopic::factory()->create(['forum_category_id' => $this->category->id])->id,
        ]);

        Sanctum::actingAs($this->opener);

        foreach ([$own, $pending, $elsewhere] as $post) {
            $this->putJson($this->url('/accepted-answer'), ['post_id' => $post->id])->assertUnprocessable();
        }
    }

    public function test_a_removed_reply_stops_being_the_answer(): void
    {
        $post = ForumPost::factory()->create(['forum_topic_id' => $this->topic->id]);
        $this->topic->forceFill(['accepted_post_id' => $post->id])->save();

        $moderator = User::factory()->create(['user_kind' => UserKind::Staff]);
        $moderator->assignRole('Moderator');
        $post->reject($moderator, 'spam');

        $this->assertNull($this->topic->fresh()->accepted_post_id);
        $this->getJson($this->url())->assertJsonPath('data.topic.accepted_answer', null);
    }

    public function test_switching_accepted_answers_off_hides_them(): void
    {
        $post = ForumPost::factory()->create(['forum_topic_id' => $this->topic->id]);
        $this->topic->forceFill(['accepted_post_id' => $post->id])->save();
        $this->setting('forum_accepted_answers_enabled', false);

        $this->getJson($this->url())
            ->assertJsonPath('data.topic.accepted_answer', null)
            ->assertJsonPath('data.posts.0.is_accepted', false);
        $this->getJson('/api/v1/forum/categories/general/topics')
            ->assertJsonPath('data.0.has_accepted_answer', false);

        Sanctum::actingAs($this->opener);
        $this->putJson($this->url('/accepted-answer'), ['post_id' => $post->id])->assertNotFound();
    }

    // ── Profile cards ───────────────────────────────────────────────────

    public function test_pasted_profile_links_become_cards(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrova', 'full_name' => 'д-р Ана Петрова', 'city' => 'Битола']);
        Facility::factory()->create(['slug' => 'klinika-sonce', 'name' => 'Клиника Сонце', 'type' => FacilityType::Hospital]);
        Facility::factory()->pharmacy()->create(['slug' => 'apteka-zdravje', 'name' => 'Аптека Здравје']);
        Doctor::factory()->unpublished()->create(['slug' => 'skrien']);

        ForumPost::factory()->create([
            'forum_topic_id' => $this->topic->id,
            'body' => "Одете кај https://zdravje360.test/doctors/ana-petrova и види /facilities/klinika-sonce.\n"
                .'Лекови: https://www.zdravje360.test/pharmacies/apteka-zdravje, https://zdravje360.test/doctors/skrien',
        ]);

        $cards = $this->getJson($this->url())->assertOk()->json('data.posts.0.profile_cards');

        $this->assertSame(['doctor', 'facility', 'pharmacy'], array_column($cards, 'kind'));
        $this->assertSame('д-р Ана Петрова', $cards[0]['name']);
        $this->assertSame('/doctors/ana-petrova', $cards[0]['path']);
        $this->assertSame('Битола', $cards[0]['city']);
        $this->assertSame('/pharmacies/apteka-zdravje', $cards[2]['path']);
    }

    public function test_only_this_sites_links_count_and_at_most_three(): void
    {
        $this->assertSame([], ForumProfileLinks::extract('https://evil.example/doctors/ana-petrova'));
        $this->assertSame([], ForumProfileLinks::extract('see example.com/doctors/ana-petrova'));
        $this->assertSame(
            [['doctors', 'a'], ['doctors', 'b'], ['facilities', 'c']],
            ForumProfileLinks::extract('/doctors/a /doctors/a /doctors/b (/facilities/c) /doctors/d'),
        );
    }

    public function test_switching_profile_cards_off_leaves_plain_text(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrova']);
        $this->topic->update(['body' => 'Препорака: /doctors/ana-petrova']);
        $this->setting('forum_profile_cards_enabled', false);

        $this->getJson($this->url())->assertJsonPath('data.topic.profile_cards', []);
    }
}
