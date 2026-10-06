<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ForumContentStatus;
use App\Enums\RemovalCategory;
use App\Enums\ReportReason;
use App\Enums\ReviewStatus;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Removal is never silent: a review or forum reply that was published and
 * later taken down stays in its list as a placeholder with the date and a
 * public category — never its text, rating, author or the moderator's note.
 */
class ReviewTombstoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        SiteSetting::current()->update(['public_forum' => true]);
    }

    private function doctor(): Doctor
    {
        return Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
    }

    private function review(Doctor $doctor, int $rating, array $attributes = []): Review
    {
        return Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'rating' => $rating,
            ...$attributes,
        ]);
    }

    public function test_a_published_review_hidden_after_a_report_leaves_a_placeholder_with_date_and_category(): void
    {
        $doctor = $this->doctor();
        $kept = $this->review($doctor, 5, ['published_at' => now()->subDays(1), 'body' => 'Одличен лекар, многу внимателен.']);
        $removed = $this->review($doctor, 1, ['published_at' => now()->subDays(3), 'body' => 'Навредлив текст за лекарот.']);
        ContentReport::factory()->about($removed)->create(['reason' => ReportReason::Abuse]);
        ContentReport::factory()->about($removed)->create(['reason' => ReportReason::Abuse]);
        ContentReport::factory()->about($removed)->create(['reason' => ReportReason::Spam]);

        $this->travel(1)->hours();
        ContentReport::query()->firstOrFail()->hideContent(User::factory()->create(), 'Интерна белешка за авторот.');

        $removed->refresh();
        $this->assertSame(ReviewStatus::Rejected, $removed->status);
        $this->assertSame(RemovalCategory::Abuse, $removed->removal_category);
        $this->assertNotNull($removed->removed_at);
        $this->assertNotNull($removed->published_at, 'kept so the placeholder stays where the review was');

        $response = $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $kept->id)
            ->assertJsonPath('data.1', [
                'id' => $removed->id,
                'removed' => true,
                'removed_at' => $removed->removed_at->toIso8601String(),
                'removal_category' => 'abuse',
            ])
            ->assertJsonPath('meta.total', 2);

        $json = $response->getContent();
        $this->assertStringNotContainsString('Навредлив текст', $json);
        $this->assertStringNotContainsString('Интерна белешка', $json);
        $this->assertStringNotContainsString($removed->user->publicName(), $json);
    }

    public function test_ratings_and_counts_exclude_the_placeholder(): void
    {
        $doctor = $this->doctor();
        $this->review($doctor, 5);
        $removed = $this->review($doctor, 1);

        $removed->reject(User::factory()->create(), 'note', afterReport: true, category: RemovalCategory::Spam);

        $doctor->refresh();
        $this->assertSame(1, (int) $doctor->reviews_count);
        $this->assertEqualsWithDelta(5.0, (float) $doctor->rating_avg, 0.001);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.rating_counts.1', 0)
            ->assertJsonPath('meta.rating_counts.5', 1)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/doctors/ana-petrovska')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 1);
    }

    public function test_a_review_refused_before_publication_leaves_no_trace(): void
    {
        $doctor = $this->doctor();
        $pending = Review::factory()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);

        $pending->reject(User::factory()->create(), 'Не е во согласност со правилата.');

        $pending->refresh();
        $this->assertNull($pending->removed_at);
        $this->assertNull($pending->removal_category);
        $this->assertNull($pending->published_at);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_a_star_filter_leaves_placeholders_out_and_rating_sorts_put_them_last(): void
    {
        $doctor = $this->doctor();
        $low = $this->review($doctor, 1, ['published_at' => now()->subDays(2)]);
        $removed = $this->review($doctor, 5, ['published_at' => now()->subDay()]);
        $removed->reject(User::factory()->create(), 'note', afterReport: true);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews?rating=5')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Ordered by its hidden 5 stars it would come first.
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews?sort=rating_high')
            ->assertOk()
            ->assertJsonPath('data.0.id', $low->id)
            ->assertJsonPath('data.1.removed', true)
            ->assertJsonPath('data.1.removal_category', 'other');
    }

    public function test_re_approving_a_removed_review_clears_the_placeholder(): void
    {
        $doctor = $this->doctor();
        $review = $this->review($doctor, 4);
        $moderator = User::factory()->create();
        $review->reject($moderator, 'note', afterReport: true, category: RemovalCategory::FalseInformation);

        $review->approve($moderator);

        $review->refresh();
        $this->assertNull($review->removed_at);
        $this->assertNull($review->removal_category);
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonMissingPath('data.0.removed');
    }

    public function test_a_moderator_picked_category_wins_over_the_report_reason(): void
    {
        $review = $this->review($this->doctor(), 2);
        $report = ContentReport::factory()->about($review)->create(['reason' => ReportReason::Other]);

        $report->hideContent(User::factory()->create(), 'note', RemovalCategory::Illegal);

        $this->assertSame(RemovalCategory::Illegal, $review->fresh()->removal_category);
    }

    public function test_a_forum_reply_removed_after_publication_leaves_a_placeholder_in_its_thread(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'opsto']);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'slug' => 'tema']);
        $first = ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'published_at' => now()->subHours(3)]);
        $removed = ForumPost::factory()->create([
            'forum_topic_id' => $topic->id,
            'published_at' => now()->subHours(2),
            'body' => 'Купете го овој лек на мојот сајт.',
        ]);
        $last = ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'published_at' => now()->subHour()]);
        $pending = ForumPost::factory()->pending()->create(['forum_topic_id' => $topic->id]);
        $pending->reject(User::factory()->create(), 'Не е во согласност со правилата.');

        ContentReport::factory()->about($removed)->create(['reason' => ReportReason::Spam])
            ->hideContent(User::factory()->create(), 'Реклама.');

        $this->assertSame(ForumContentStatus::Rejected, $removed->fresh()->status);

        $response = $this->getJson('/api/v1/forum/categories/opsto/topics/tema')->assertOk();

        $response->assertJsonCount(3, 'data.posts')
            ->assertJsonPath('data.posts.0.id', $first->id)
            ->assertJsonPath('data.posts.1.id', $removed->id)
            ->assertJsonPath('data.posts.1.removed', true)
            ->assertJsonPath('data.posts.1.removal_category', 'spam')
            ->assertJsonMissingPath('data.posts.1.body')
            ->assertJsonMissingPath('data.posts.1.author_name')
            ->assertJsonPath('data.posts.2.id', $last->id);
        $this->assertStringNotContainsString('мојот сајт', $response->getContent());
    }

    public function test_the_migration_backfills_items_hidden_from_the_report_queue_only(): void
    {
        $doctor = $this->doctor();
        $hidden = $this->review($doctor, 1);
        $refused = Review::factory()->rejected()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        DB::table('reviews')->where('id', $hidden->id)->update(['status' => 'rejected', 'published_at' => null]);
        foreach (['personal_data', 'personal_data', 'abuse'] as $reason) {
            ContentReport::factory()->about($hidden)->create(['reason' => $reason, 'status' => 'hidden', 'resolved_at' => '2026-09-01 10:00:00']);
        }

        $migration = require database_path('migrations/2026_10_14_120000_add_removal_fields_to_reviews_and_forum_content.php');
        $migration->down();
        $migration->up();

        $hidden->refresh();
        $this->assertSame(RemovalCategory::PersonalData, $hidden->removal_category);
        $this->assertSame('2026-09-01 10:00:00', $hidden->removed_at?->format('Y-m-d H:i:s'));
        $this->assertNull($refused->fresh()->removed_at);

        // Backfilled placeholders have no publication date; they sort by removal date.
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.removal_category', 'personal_data');
    }
}
