<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\ForumTopics\Pages\ViewForumTopic;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumTag;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Forum keywords, tag pages and related-topic links (docs/seo.md).
 */
class ForumTagTest extends TestCase
{
    use RefreshDatabase;

    private ForumCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ForumCategory::factory()->create(['slug' => 'general']);
    }

    /**
     * @param  list<string>  $tags
     */
    private function topic(string $title, array $tags = [], bool $confirmed = false, ?ForumCategory $category = null): ForumTopic
    {
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => ($category ?? $this->category)->id,
            'title' => $title,
            'last_post_at' => now(),
        ]);
        $topic->syncTags($tags, $confirmed);

        return $topic;
    }

    public function test_member_can_suggest_up_to_five_normalised_tags_when_creating_a_topic(): void
    {
        SiteSetting::current()->update(['forum_topics_require_moderation' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'Операција за проширени вени',
            'body' => 'Кој има искуство со операција на вени во Скопје?',
            'accepted_community_rules' => true,
            'tags' => ['#Проширени вени', 'prosireni veni', 'Операција'],
        ])->assertCreated();

        $topic = ForumTopic::query()->sole();
        $this->assertSame(['проширени вени', 'операција'], $topic->tags()->pluck('name')->all());
        $this->assertFalse((bool) $topic->tags()->first()?->pivot?->getAttribute('confirmed'));
        $this->assertSame('prosireni-veni', ForumTag::query()->where('name', 'проширени вени')->value('slug'));
        $this->assertSame('prosireni veni', ForumTag::query()->where('name', 'проширени вени')->value('latin'));
    }

    public function test_more_than_five_or_invalid_tags_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = [
            'title' => 'Операција за проширени вени',
            'body' => 'Кој има искуство со операција на вени во Скопје?',
            'accepted_community_rules' => true,
        ];

        $this->postJson('/api/v1/forum/categories/general/topics', [...$payload, 'tags' => ['а1', 'б2', 'в3', 'г4', 'д5', 'ѓ6']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');

        $this->postJson('/api/v1/forum/categories/general/topics', [...$payload, 'tags' => ['2026']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags.0');

        $this->assertSame(0, ForumTopic::query()->count());
    }

    public function test_one_tag_row_serves_every_spelling(): void
    {
        $this->topic('Прва', ['проширени вени']);
        $this->topic('Втора', ['proshireni veni']);
        $this->topic('Трета', ['prošireni veni']);

        $this->assertSame(1, ForumTag::query()->count());
        $this->assertSame('проширени вени', ForumTag::query()->value('name'));
    }

    public function test_a_keyword_created_concurrently_is_reused_not_a_500(): void
    {
        // Another request inserts the same keyword right after our lookup
        // found nothing, before our insert.
        $raced = false;
        DB::listen(function (QueryExecuted $query) use (&$raced): void {
            if ($raced || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"forum_tags"') || ! str_contains($query->sql, 'match_key')) {
                return;
            }

            $raced = true;
            DB::table('forum_tags')->insert([
                'name' => 'проширени вени',
                'match_key' => $query->bindings[0],
                'latin' => 'prosireni veni',
                'slug' => 'prosireni-veni-raced',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->topic('Прва', ['проширени вени']);

        $this->assertTrue($raced);
        $this->assertSame(1, ForumTag::query()->count());
        $this->assertSame(['prosireni-veni-raced'], ForumTopic::query()->sole()->tags->pluck('slug')->all());
    }

    public function test_topic_detail_carries_tags_and_last_post_at(): void
    {
        $topic = $this->topic('Операција за проширени вени', ['проширени вени']);

        $this->getJson("/api/v1/forum/categories/general/topics/{$topic->slug}")
            ->assertOk()
            ->assertJsonPath('data.topic.tags.0.name', 'проширени вени')
            ->assertJsonPath('data.topic.tags.0.slug', 'prosireni-veni')
            ->assertJsonPath('data.topic.tags.0.latin', 'prosireni veni')
            ->assertJsonPath('data.topic.last_post_at', $topic->last_post_at?->toIso8601String());
    }

    public function test_related_topics_rank_shared_tags_first_then_fill_from_the_category(): void
    {
        $other = ForumCategory::factory()->create(['slug' => 'surgery']);
        $topic = $this->topic('Операција за проширени вени', ['проширени вени', 'операција']);
        $twoShared = $this->topic('Ласер или операција за вени', ['проширени вени', 'операција'], category: $other);
        $oneShared = $this->topic('Чорапи за проширени вени', ['проширени вени']);
        $sameCategory = $this->topic('Прашање за вакцини');
        ForumTopic::factory()->pending()->create(['forum_category_id' => $this->category->id])
            ->syncTags(['проширени вени'], false);

        $response = $this->getJson("/api/v1/forum/categories/general/topics/{$topic->slug}")->assertOk();

        $this->assertSame(
            [$twoShared->slug, $oneShared->slug, $sameCategory->slug],
            array_column($response->json('data.related_topics'), 'slug'),
        );
        // Cross-category links need the category to build the URL.
        $response->assertJsonPath('data.related_topics.0.category.slug', 'surgery');
    }

    public function test_tag_page_lists_visible_topics_and_404s_without_any(): void
    {
        $this->topic('Прва тема', ['проширени вени']);
        $this->topic('Втора тема', ['проширени вени']);
        ForumTopic::factory()->pending()->create(['forum_category_id' => $this->category->id])
            ->syncTags(['само чека'], false);

        $this->getJson('/api/v1/forum/tags/prosireni-veni')
            ->assertOk()
            ->assertJsonPath('data.tag.name', 'проширени вени')
            ->assertJsonPath('data.tag.topics_count', 2)
            ->assertJsonCount(2, 'data.topics')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/forum/tags/samo-ceka')->assertNotFound();
        $this->getJson('/api/v1/forum/tags/nema')->assertNotFound();
    }

    public function test_tag_index_filters_by_minimum_visible_topics(): void
    {
        foreach (['Прва', 'Втора', 'Трета'] as $title) {
            $this->topic($title, ['проширени вени']);
        }
        $this->topic('Четврта', ['ретко']);

        $this->getJson('/api/v1/forum/tags?min_topics=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'prosireni-veni')
            ->assertJsonPath('data.0.topics_count', 3);

        $this->getJson('/api/v1/forum/tags')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_search_finds_a_cyrillic_topic_by_a_latin_keyword(): void
    {
        $topic = $this->topic('Искуства со клиника во Скопје', ['проширени вени']);
        $this->topic('Нешто друго', ['вакцини']);

        $this->getJson('/api/v1/forum/topics?q=prosireni')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $topic->slug);
    }

    public function test_doctor_related_topics_use_confirmed_tags_only(): void
    {
        // Profiles carry the title in the name; keywords may or may not.
        Doctor::factory()->create(['full_name' => 'д-р Елена Димитрова', 'slug' => 'elena-dimitrova']);
        $confirmed = $this->topic('Искуство кај кардиолог', ['Елена Димитрова'], confirmed: true);
        $withTitle = $this->topic('Втор преглед', ['Dr. Elena Dimitrova'], confirmed: true);
        $this->topic('Само предлог', ['elena dimitrova'], confirmed: false);
        $this->topic('Само презиме', ['Димитрова'], confirmed: true);
        $this->topic('Елена Димитрова во наслов');

        $slugs = array_column(
            $this->getJson('/api/v1/forum/topics/related?doctor=elena-dimitrova')->assertOk()->json('data'),
            'slug',
        );
        sort($slugs);
        $expected = [$confirmed->slug, $withTitle->slug];
        sort($expected);
        $this->assertSame($expected, $slugs);

        $this->getJson('/api/v1/forum/topics/related?doctor=nema')->assertNotFound();
        $this->getJson('/api/v1/forum/topics/related')->assertUnprocessable();
        $this->getJson('/api/v1/forum/topics/related?doctor=a&facility=b')->assertUnprocessable();
    }

    public function test_facility_related_topics_match_tag_or_title(): void
    {
        Facility::factory()->create(['name' => 'Клиника Софија', 'slug' => 'klinika-sofija', 'type' => 'clinic']);
        $byTag = $this->topic('Искуство со операција', ['klinika sofija']);
        $byTitle = $this->topic('Цени во Клиника Софија');
        $this->topic('Друга болница');

        $slugs = array_column(
            $this->getJson('/api/v1/forum/topics/related?facility=klinika-sofija')->assertOk()->json('data'),
            'slug',
        );

        sort($slugs);
        $expected = [$byTag->slug, $byTitle->slug];
        sort($expected);
        $this->assertSame($expected, $slugs);
    }

    public function test_staff_edit_keywords_in_the_admin_panel_and_confirm_them(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $topic = $this->topic('Тема', ['предлог']);

        $admin = User::factory()->staff()->create();
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);

        Livewire::test(ViewForumTopic::class, ['record' => $topic->getRouteKey()])
            ->assertSee('предлог')
            ->callAction('editKeywords', data: ['tags' => ['Проширени вени', 'операција']])
            ->assertHasNoActionErrors();

        $tags = $topic->tags()->get();
        // In the order given: the first keyword is the main one.
        $this->assertSame(['проширени вени', 'операција'], $tags->pluck('name')->all());
        $this->assertTrue($tags->every(fn (ForumTag $tag): bool => (bool) $tag->pivot?->getAttribute('confirmed')));
    }
}
