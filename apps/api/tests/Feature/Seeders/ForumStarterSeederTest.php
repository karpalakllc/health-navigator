<?php

namespace Tests\Feature\Seeders;

use App\Enums\ForumContentStatus;
use App\Enums\UserKind;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use Database\Seeders\ForumStarterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumStarterSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array<string, mixed>>
     */
    private function content(): array
    {
        return require database_path('seeders/data/forum_starter.php');
    }

    private function topicCount(): int
    {
        return array_sum(array_map(fn (array $category): int => count($category['topics']), $this->content()));
    }

    private function author(): User
    {
        return User::query()->where('email', config('zdravje.seed.forum_author.email'))->firstOrFail();
    }

    public function test_it_seeds_published_categories_and_pinned_approved_topics(): void
    {
        $this->seed(ForumStarterSeeder::class);

        $content = $this->content();
        $this->assertGreaterThanOrEqual(12, count($content));
        $this->assertSame(count($content), ForumCategory::query()->published()->count());
        $this->assertSame($this->topicCount(), ForumTopic::query()->count());

        foreach ($content as $data) {
            $category = ForumCategory::query()->where('slug', $data['slug'])->firstOrFail();
            $this->assertSame($data['name'], $category->name);
            $this->assertGreaterThanOrEqual(2, $category->topics()->count());
        }

        ForumTopic::query()->each(function (ForumTopic $topic): void {
            $this->assertSame(ForumContentStatus::Approved, $topic->status);
            $this->assertTrue($topic->is_pinned);
            $this->assertNotNull($topic->published_at);
            $this->assertNotNull($topic->last_post_at);
            $this->assertSame($this->author()->id, $topic->user_id);
            // Indexed for search like any approved topic.
            $this->assertTrue($topic->shouldBeSearchable());
        });

        $this->assertTrue(ForumTopic::query()
            ->where('slug', 'dobredojdovte-kako-da-go-koristite-forumot')
            ->firstOrFail()
            ->is_locked);
    }

    public function test_the_author_is_a_staff_account_shown_as_administrator(): void
    {
        $this->seed(ForumStarterSeeder::class);

        $author = $this->author();
        $this->assertSame(UserKind::Staff, $author->user_kind);
        $this->assertSame('Администратор', $author->publicName());
        $this->assertNotNull($author->email_verified_at);
        $this->assertFalse($author->must_choose_username);
        // A badge, not powers: no role, so no panel or moderation rights.
        $this->assertFalse($author->roles()->exists());

        $this->getJson('/api/v1/forum/categories/general-health/topics/dobredojdovte-kako-da-go-koristite-forumot')
            ->assertOk()
            ->assertJsonPath('data.topic.author.name', 'Администратор')
            ->assertJsonPath('data.topic.author.is_team_member', true)
            ->assertJsonPath('data.topic.is_pinned', true);
    }

    public function test_the_topics_count_toward_the_category_listing(): void
    {
        $this->seed(ForumStarterSeeder::class);

        $expected = collect($this->content())->mapWithKeys(fn (array $c): array => [$c['slug'] => count($c['topics'])]);

        $categories = collect($this->getJson('/api/v1/forum/categories')->assertOk()->json('data'));

        $this->assertSame($expected->count(), $categories->count());

        foreach ($categories as $category) {
            $this->assertSame($expected[$category['slug']], $category['topics_count'], $category['slug']);
        }
    }

    public function test_running_it_again_creates_nothing_new(): void
    {
        $this->seed(ForumStarterSeeder::class);
        $this->seed(ForumStarterSeeder::class);

        $this->assertSame(count($this->content()), ForumCategory::query()->count());
        $this->assertSame($this->topicCount(), ForumTopic::query()->count());
        $this->assertSame(1, User::query()->where('username', 'Администратор')->count());
    }

    public function test_a_rerun_keeps_what_the_team_changed(): void
    {
        $this->seed(ForumStarterSeeder::class);

        $category = ForumCategory::query()->where('slug', 'stomatologija')->firstOrFail();
        $category->update(['name' => 'Заби', 'is_published' => false]);

        $topic = ForumTopic::query()->where('slug', 'predlozi-za-forumot')->firstOrFail();
        $topic->update(['is_pinned' => false, 'title' => 'Предлози (уредено)']);

        $moved = ForumTopic::query()->where('slug', 'urolog-iskustva')->firstOrFail();
        $moved->update(['forum_category_id' => ForumCategory::query()->where('slug', 'general-health')->value('id')]);

        $this->seed(ForumStarterSeeder::class);

        $category->refresh();
        $this->assertSame('Заби', $category->name);
        $this->assertFalse($category->is_published);
        $topic->refresh();
        $this->assertFalse($topic->is_pinned);
        $this->assertSame('Предлози (уредено)', $topic->title);
        $this->assertSame(1, ForumTopic::query()->where('slug', 'urolog-iskustva')->count());
    }

    public function test_it_upgrades_the_earlier_seeded_categories_without_changing_their_slugs(): void
    {
        ForumCategory::query()->create([
            'slug' => 'general-health',
            'name' => 'Општо здравје',
            'description' => 'Општа дискусија и практични совети (само информативно). Не е медицински совет.',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        ForumCategory::query()->create([
            'slug' => 'ishrana-i-zhivot',
            'name' => 'Исхрана и навики',
            'description' => 'Оброци, хидратација, сон и движење — без диети екстремни без лекар.',
            'sort_order' => 2,
            'is_published' => true,
        ]);

        $this->seed(ForumStarterSeeder::class);

        $nutrition = ForumCategory::query()->where('slug', 'ishrana-i-zhivot')->sole();
        $this->assertSame('Исхрана, вежбање и слабеење', $nutrition->name);
        $this->assertStringNotContainsString('без лекар', (string) $nutrition->description);

        $general = ForumCategory::query()->where('slug', 'general-health')->sole();
        $this->assertStringNotContainsString('медицински совет', (string) $general->description);
        $this->assertSame(count($this->content()), ForumCategory::query()->count());
    }

    public function test_it_refuses_to_reuse_a_name_another_account_holds(): void
    {
        $other = new User;
        $other->forceFill([
            'name' => 'Someone',
            'username' => 'Administrator',
            'email' => 'someone@example.test',
            'password' => 'secret-password',
            'user_kind' => UserKind::Client,
        ])->save();

        $this->expectException(\RuntimeException::class);

        $this->seed(ForumStarterSeeder::class);
    }

    public function test_no_text_carries_emergency_numbers_disclaimers_or_advice_formulas(): void
    {
        $banned = [
            '/\b194\b/u', '/\b112\b/u', '/итн[аио]/iu', '/итен/iu', '/медицински совет/iu',
            '/информативн/iu', '/не е замена/iu', '/консултирајте/iu', '/дијагностицира/iu',
        ];

        foreach ($this->content() as $category) {
            $texts = [$category['name'], $category['description']];

            foreach ($category['topics'] as $topic) {
                $texts[] = $topic['title'];
                $texts[] = $topic['body'];
                $this->assertLessThanOrEqual(255, mb_strlen($topic['title']));
                $this->assertGreaterThanOrEqual(20, mb_strlen($topic['body']));
            }

            foreach ($texts as $text) {
                foreach ($banned as $pattern) {
                    $this->assertDoesNotMatchRegularExpression($pattern, $text);
                }
            }
        }
    }
}
