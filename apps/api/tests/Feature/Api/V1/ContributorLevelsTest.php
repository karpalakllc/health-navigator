<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AnonymiseUser;
use App\Enums\ReviewStatus;
use App\Models\ContributorLevel;
use App\Models\Doctor;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ForumPostHelpfulVotes;
use App\Support\Levels\ContributorLevels;
use App\Support\Levels\LevelRules;
use App\Support\ReviewHelpfulVotes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * W8-C: contributor levels from public content only, with the anti-gaming
 * rules of docs/levels.md, the chips in the public payloads, the member's own
 * progress and the monthly top lists.
 */
class ContributorLevelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::current()->update(['public_forum' => true]);
        Cache::flush();
    }

    private function review(User $author, array $attributes = []): Review
    {
        return Review::factory()->approved()->create([
            'user_id' => $author->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::factory()->create(['is_published' => true])->id,
            ...$attributes,
        ]);
    }

    private function level(User $user): ?ContributorLevel
    {
        return ContributorLevel::query()->find($user->id);
    }

    public function test_only_approved_reviews_earn_points_and_approval_updates_the_level_at_once(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $pending = Review::factory()->create(['user_id' => $author->id]);
        Review::factory()->rejected()->create(['user_id' => $author->id]);

        $this->assertNull($this->level($author), 'pending or refused content earns nothing');

        $pending->approve($moderator);

        $level = $this->level($author);
        $this->assertSame(1, $level->reviews_count);
        $this->assertSame(LevelRules::REVIEW_POINTS, $level->review_points);
        $this->assertSame(1, $level->review_level);
    }

    public function test_a_review_taken_down_after_publication_costs_more_than_it_earned(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $reviews = collect(range(1, 3))->map(fn () => $this->review($author));

        $this->assertSame(30, $this->level($author)->review_points);

        $reviews->first()->reject($moderator, 'Navreda');

        $level = $this->level($author);
        $this->assertSame(2, $level->reviews_count);
        $this->assertSame(2 * LevelRules::REVIEW_POINTS - LevelRules::REVIEW_REMOVED_PENALTY, $level->review_points);
    }

    public function test_reviews_count_for_points_at_most_five_per_day_of_submission(): void
    {
        $author = User::factory()->create();
        $day = CarbonImmutable::parse('2026-09-10 09:00', LevelRules::TIMEZONE);

        foreach (range(1, 7) as $i) {
            $this->review($author, ['created_at' => $day->addMinutes($i)]);
        }

        $level = $this->level($author);
        $this->assertSame(7, $level->reviews_count);
        $this->assertSame(LevelRules::REVIEWS_PER_DAY * LevelRules::REVIEW_POINTS, $level->review_points);
    }

    public function test_helpful_votes_from_one_member_and_on_one_review_are_capped_and_suspended_voters_do_not_count(): void
    {
        $author = User::factory()->create();
        $reviews = collect(range(1, 5))->map(fn () => $this->review($author));
        $friend = User::factory()->create();

        // One member voting on all five reviews: only three count.
        $reviews->each(fn (Review $review) => ReviewHelpfulVotes::add($review, $friend));
        $this->assertSame(3, $this->level($author)->review_helpful_count);

        // Twelve more members on the last review: ten count for it (the
        // friend's vote there was already over the friend's own limit).
        foreach (range(1, 12) as $ignored) {
            ReviewHelpfulVotes::add($reviews->last(), User::factory()->create());
        }
        $this->assertSame(13, $this->level($author)->review_helpful_count);

        $suspended = User::factory()->create(['suspended_at' => now()]);
        ReviewHelpfulVotes::add($reviews->first(), $suspended);
        $this->assertSame(13, $this->level($author)->review_helpful_count);
    }

    public function test_a_self_vote_never_counts_even_if_written_past_the_api(): void
    {
        $author = User::factory()->create();
        $review = $this->review($author);
        DB::table('review_helpful_votes')->insert(['review_id' => $review->id, 'user_id' => $author->id, 'created_at' => now()]);

        $this->assertSame(0, ContributorLevels::recompute($author->id)->review_helpful_count);
    }

    public function test_forum_replies_earn_points_only_in_other_members_topics_and_are_capped_per_day(): void
    {
        $member = User::factory()->create();
        $own = ForumTopic::factory()->create(['user_id' => $member->id]);
        $other = ForumTopic::factory()->create();

        ForumPost::factory()->count(2)->create(['forum_topic_id' => $own->id, 'user_id' => $member->id]);
        $level = $this->level($member);
        $this->assertSame(1, $level->forum_topics_count);
        $this->assertSame(0, $level->forum_replies_count);
        $this->assertSame(LevelRules::TOPIC_POINTS, $level->forum_points);

        $day = CarbonImmutable::parse('2026-09-10 09:00', LevelRules::TIMEZONE);
        foreach (range(1, 10) as $i) {
            ForumPost::factory()->create(['forum_topic_id' => $other->id, 'user_id' => $member->id, 'created_at' => $day->addMinutes($i)]);
        }

        $level = $this->level($member);
        $this->assertSame(10, $level->forum_replies_count);
        $this->assertSame(LevelRules::TOPIC_POINTS + LevelRules::REPLIES_PER_DAY * LevelRules::REPLY_POINTS, $level->forum_points);
        $this->assertSame(2, $level->forum_level);
    }

    public function test_pending_forum_content_earns_nothing_and_a_removed_reply_subtracts(): void
    {
        $member = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $topic = ForumTopic::factory()->create();
        ForumPost::factory()->pending()->create(['forum_topic_id' => $topic->id, 'user_id' => $member->id]);
        $this->assertNull($this->level($member));

        $replies = ForumPost::factory()->count(4)->create(['forum_topic_id' => $topic->id, 'user_id' => $member->id]);
        $this->assertSame(20, $this->level($member)->forum_points);

        $replies->first()->reject($moderator, 'spam');

        $this->assertSame(3 * LevelRules::REPLY_POINTS - LevelRules::FORUM_REMOVED_PENALTY, $this->level($member)->forum_points);
    }

    public function test_members_mark_forum_replies_helpful_but_never_their_own(): void
    {
        $author = User::factory()->create();
        $reply = ForumPost::factory()->create(['user_id' => $author->id]);

        Sanctum::actingAs($author);
        $this->putJson("/api/v1/forum/posts/{$reply->id}/helpful")->assertUnprocessable()->assertJsonValidationErrors('post');

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/forum/posts/{$reply->id}/helpful")
            ->assertOk()
            ->assertExactJson(['data' => ['helpful_count' => 1, 'has_voted_helpful' => true]]);
        $this->putJson("/api/v1/forum/posts/{$reply->id}/helpful")->assertOk()->assertJsonPath('data.helpful_count', 1);
        $this->assertSame(1, $this->level($author)->forum_helpful_count);

        $this->deleteJson("/api/v1/forum/posts/{$reply->id}/helpful")
            ->assertOk()
            ->assertExactJson(['data' => ['helpful_count' => 0, 'has_voted_helpful' => false]]);
        $this->assertSame(0, $this->level($author)->forum_helpful_count);

        $pending = ForumPost::factory()->pending()->create();
        $this->putJson("/api/v1/forum/posts/{$pending->id}/helpful")->assertNotFound();
    }

    public function test_the_topic_page_shows_the_forum_level_helpful_count_and_the_viewers_vote(): void
    {
        $topic = ForumTopic::factory()->create();
        $helper = User::factory()->create();
        // Six replies (30 points) and three votes (9): „Помошник“ needs 5 posts and 35.
        $replies = ForumPost::factory()->count(6)->create(['forum_topic_id' => $topic->id, 'user_id' => $helper->id]);
        $viewer = User::factory()->create();
        ForumPostHelpfulVotes::add($replies->first(), $viewer);
        ForumPostHelpfulVotes::add($replies->first(), User::factory()->create());
        ForumPostHelpfulVotes::add($replies->first(), User::factory()->create());

        Sanctum::actingAs($viewer);
        $response = $this->getJson("/api/v1/forum/categories/{$topic->category->slug}/topics/{$topic->slug}")->assertOk();

        $response->assertJsonPath('data.posts.0.author.level', 2);
        $response->assertJsonPath('data.posts.0.helpful_count', 3);
        $response->assertJsonPath('data.posts.0.viewer.has_voted_helpful', true);
        $response->assertJsonPath('data.posts.1.viewer.has_voted_helpful', false);
        // The topic opener has one topic: „Соговорник“.
        $response->assertJsonPath('data.topic.author.level', 1);
    }

    public function test_review_lists_show_the_reviewer_level_but_never_for_staff_suspended_or_deleted_accounts(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana', 'is_published' => true]);
        $member = User::factory()->create();
        $staff = User::factory()->moderator()->create();
        $suspended = User::factory()->create();

        foreach ([$member, $staff, $suspended] as $author) {
            $this->review($author, ['reviewable_id' => $doctor->id]);
        }
        $suspended->forceFill(['suspended_at' => now()])->save();

        $levels = collect($this->getJson('/api/v1/doctors/ana/reviews')->assertOk()->json('data'))
            ->mapWithKeys(fn (array $review): array => [$review['author_name'] => $review['author_level']]);

        $this->assertSame(1, $levels[$member->username]);
        $this->assertNull($levels[$staff->username]);
        $this->assertNull($levels[$suspended->username]);
    }

    public function test_the_member_sees_their_progress_towards_the_next_level(): void
    {
        $member = User::factory()->create();
        $this->review($member);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/me/levels')
            ->assertOk()
            ->assertJsonPath('data.shown_publicly', true)
            ->assertJsonPath('data.reviews.level', 1)
            ->assertJsonPath('data.reviews.points', 10)
            ->assertJsonPath('data.reviews.next', ['level' => 2, 'missing_reviews' => 2, 'missing_points' => 25])
            ->assertJsonPath('data.forum.level', 0)
            ->assertJsonPath('data.forum.next', ['level' => 1, 'missing_posts' => 1, 'missing_points' => 3]);

    }

    public function test_the_levels_endpoint_needs_a_session(): void
    {
        $this->getJson('/api/v1/me/levels')->assertUnauthorized();
    }

    public function test_the_monthly_lists_cover_only_last_calendar_month_by_username_without_staff_or_suspended_members(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', LevelRules::TIMEZONE));
        $september = CarbonImmutable::parse('2026-09-15 10:00', LevelRules::TIMEZONE);

        $best = User::factory()->create(['username' => 'najdobar']);
        $second = User::factory()->create(['username' => 'vtor']);
        $staff = User::factory()->moderator()->create(['username' => 'timce']);
        $suspended = User::factory()->create(['username' => 'suspendiran']);
        $october = User::factory()->create(['username' => 'oktomvri']);

        foreach ([[$best, 3], [$second, 1], [$staff, 4], [$suspended, 4]] as [$author, $count]) {
            foreach (range(1, $count) as $ignored) {
                $this->review($author, ['created_at' => $september, 'published_at' => $september]);
            }
        }
        $this->review($october, ['published_at' => now()]);
        $suspended->forceFill(['suspended_at' => now()])->save();

        $topic = ForumTopic::factory()->create(['published_at' => $september, 'created_at' => $september]);
        ForumPost::factory()->count(2)->create(['forum_topic_id' => $topic->id, 'user_id' => $second->id, 'published_at' => $september, 'created_at' => $september]);

        $data = $this->getJson('/api/v1/community/leaderboards')->assertOk()->json('data');

        $this->assertSame('2026-09', $data['month']);
        $this->assertSame(['najdobar', 'vtor'], array_column($data['reviewers'], 'username'));
        // Three reviews without „Корисно“ votes: 30 points, still „Рецензент“ (level 2 needs 35).
        $this->assertSame(['username' => 'najdobar', 'level' => 1, 'points' => 30, 'reviews' => 3, 'helpful' => 0], $data['reviewers'][0]);
        $this->assertSame('vtor', $data['forum'][0]['username']);
        $this->assertSame(2, $data['forum'][0]['replies']);

        // Only usernames and public tallies: no ids, names or e-mail.
        $this->assertStringNotContainsString($best->email, json_encode($data));
        $this->assertArrayNotHasKey('user_id', $data['reviewers'][0]);
    }

    public function test_the_forum_list_is_withheld_while_the_forum_module_is_off(): void
    {
        SiteSetting::current()->update(['public_forum' => false]);

        $this->getJson('/api/v1/community/leaderboards')->assertOk()->assertJsonPath('data.forum', null);
    }

    public function test_the_lists_carry_the_rules_so_the_page_explains_the_real_numbers(): void
    {
        $this->getJson('/api/v1/community/leaderboards')
            ->assertOk()
            ->assertJsonPath('data.rules.reviews.review_points', LevelRules::REVIEW_POINTS)
            ->assertJsonPath('data.rules.reviews.ladder.1', ['level' => 2, 'reviews' => 3, 'points' => 35])
            ->assertJsonPath('data.rules.forum.ladder.3', ['level' => 4, 'posts' => 50, 'points' => 380])
            ->assertJsonPath('data.rules.helpful_per_voter_per_author', LevelRules::HELPFUL_PER_VOTER_PER_AUTHOR);
    }

    public function test_the_lists_hold_at_most_ten_members(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', LevelRules::TIMEZONE));
        $september = CarbonImmutable::parse('2026-09-15 10:00', LevelRules::TIMEZONE);

        foreach (range(1, 12) as $ignored) {
            $this->review(User::factory()->create(), ['created_at' => $september, 'published_at' => $september]);
        }

        $this->assertCount(LevelRules::LEADERBOARD_SIZE, ContributorLevels::leaderboards()['reviewers']);
    }

    /**
     * The cached boards hold scores only: an erased, suspended or renamed
     * member is gone or renamed on the next read, not after six hours.
     */
    public function test_the_cached_lists_follow_erasure_suspension_and_renames_at_once(): void
    {
        config(['cache.default' => 'array']);
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00', LevelRules::TIMEZONE));
        $september = CarbonImmutable::parse('2026-09-15 10:00', LevelRules::TIMEZONE);
        $erased = User::factory()->create(['username' => 'izbrishan']);
        $suspended = User::factory()->create(['username' => 'suspendiran']);
        $renamed = User::factory()->create(['username' => 'staroime']);

        foreach ([$erased, $suspended, $renamed] as $author) {
            $this->review($author, ['created_at' => $september, 'published_at' => $september]);
        }

        $this->assertEqualsCanonicalizing(['izbrishan', 'suspendiran', 'staroime'], array_column(ContributorLevels::leaderboards()['reviewers'], 'username'));

        app(AnonymiseUser::class)->handle($erased);
        $suspended->forceFill(['suspended_at' => now()])->save();
        $renamed->forceFill(['username' => 'novoime'])->save();

        $this->assertSame(['novoime'], array_column(ContributorLevels::leaderboards()['reviewers'], 'username'));
        $this->assertStringNotContainsString('izbrishan', (string) json_encode(Cache::get('levels:leaderboards:v2:2026-09')));
    }

    public function test_deleting_the_account_removes_its_levels_and_the_nightly_run_does_not_bring_them_back(): void
    {
        $member = User::factory()->create();
        $this->review($member);
        $this->assertNotNull($this->level($member));

        app(AnonymiseUser::class)->handle($member);
        $this->assertNull($this->level($member));

        $this->artisan('levels:recompute')->assertSuccessful();
        $this->assertNull($this->level($member));
    }

    public function test_the_nightly_run_heals_levels_after_changes_made_without_model_events(): void
    {
        $member = User::factory()->create();
        $review = $this->review($member);
        Review::query()->whereKey($review->id)->update(['status' => ReviewStatus::Rejected->value, 'published_at' => null]);
        $this->assertSame(1, $this->level($member)->review_level);

        $this->artisan('levels:recompute')->assertSuccessful();

        $this->assertNull($this->level($member));
    }

    public function test_the_export_holds_the_members_forum_votes_and_levels(): void
    {
        $member = User::factory()->create();
        $this->review($member);
        $reply = ForumPost::factory()->create();
        ForumPostHelpfulVotes::add($reply, $member);

        Sanctum::actingAs($member);
        $export = json_decode($this->get('/api/v1/me/export')->assertOk()->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($reply->id, $export['forum_helpful_votes'][0]['forum_post_id']);
        $this->assertSame(1, $export['contributor_levels']['review_level']);
    }
}
