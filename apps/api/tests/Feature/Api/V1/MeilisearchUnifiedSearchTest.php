<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Jobs\ReindexTaxonomyMembers;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Product;
use App\Models\Review;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeMeilisearchEngine;
use Tests\TestCase;

/**
 * The production search path. Runs Scout's real Meilisearch engine against an
 * in-memory index (see FakeMeilisearchEngine for what that does and does not
 * cover) through the public /search endpoint.
 */
class MeilisearchUnifiedSearchTest extends TestCase
{
    use RefreshDatabase;

    private FakeMeilisearchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = FakeMeilisearchEngine::install();
    }

    public function test_pharmacies_and_products_are_returned_with_their_own_resources(): void
    {
        $pharmacy = Facility::factory()->create([
            'slug' => 'ana-pharmacy',
            'name' => 'Ana Pharmacy',
            'type' => FacilityType::Pharmacy,
        ]);
        Facility::factory()->create([
            'slug' => 'ana-clinic',
            'name' => 'Ana Clinic',
            'type' => FacilityType::Clinic,
        ]);
        $product = Product::factory()->create(['slug' => 'ana-product', 'name' => 'Ana Product']);
        $pharmacy->products()->attach($product->id, [
            'price' => 120,
            'currency' => 'MKD',
            'is_available' => true,
            'price_updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/search?q=ana&per_page=5')->assertOk();

        $response
            ->assertJsonPath('data.pharmacies.meta.total', 1)
            ->assertJsonPath('data.pharmacies.data.0.slug', 'ana-pharmacy')
            ->assertJsonPath('data.facilities.meta.total', 1)
            ->assertJsonPath('data.facilities.data.0.slug', 'ana-clinic')
            ->assertJsonPath('data.products.meta.total', 1)
            ->assertJsonPath('data.products.data.0.slug', 'ana-product')
            ->assertJsonPath('data.products.data.0.offer_count', 1)
            ->assertJsonPath('data.products.data.0.from_price', 120)
            ->assertJsonPath('data.grand_total', 3);

        // PharmacyListResource, not FacilityListResource.
        $this->assertSame(
            ['slug', 'name', 'city', 'avatar_url', 'cover_url', 'phone', 'office_hours', 'is_featured', 'verification', 'review_summary'],
            array_keys($response->json('data.pharmacies.data.0')),
        );

        $this->assertStringContainsString(
            'type="pharmacy"',
            $this->engine->lastSearch('facilities')['params']['filter'] ?? '',
        );
    }

    public function test_city_filter_is_applied_by_meilisearch_so_pages_are_full_and_totals_match(): void
    {
        // Lower ids — Meilisearch's first page before any city filter.
        foreach (range(1, 5) as $i) {
            Doctor::factory()->create(['full_name' => "Ana Bitola {$i}", 'city' => 'Битола']);
        }
        foreach (range(1, 4) as $i) {
            Doctor::factory()->create(['full_name' => "Ana Skopje {$i}", 'city' => 'Скопје']);
        }

        // Latin, partial input: the SQL path's script-insensitive substring match.
        $this->getJson('/api/v1/search?q=ana&city=skop&per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data.doctors.data')
            ->assertJsonPath('data.doctors.meta.total', 4)
            ->assertJsonPath('data.doctors.meta.last_page', 2)
            ->assertJsonPath('data.doctors.data.0.city', 'Скопје');

        $this->assertSame(
            'city IN ["Скопје"]',
            $this->engine->lastSearch('doctors')['params']['filter'] ?? null,
        );
    }

    public function test_city_with_no_match_returns_empty_without_querying_the_index(): void
    {
        Doctor::factory()->create(['full_name' => 'Ana One', 'city' => 'Битола']);

        $this->getJson('/api/v1/search?q=ana&city='.rawurlencode('Струга'))
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 0);

        $this->assertSame([], $this->engine->lastSearch('doctors'));
    }

    public function test_review_summary_and_primary_facility_are_resolved_on_the_meilisearch_path(): void
    {
        $doctor = Doctor::factory()->create(['full_name' => 'Ana Reviewed']);
        $facility = Facility::factory()->create(['name' => 'Hidden Wing', 'is_published' => false]);
        $doctor->facilities()->attach($facility->id, ['is_primary' => true]);
        $visible = Facility::factory()->create(['name' => 'Visible Wing']);
        $doctor->facilities()->attach($visible->id, ['is_primary' => false]);

        foreach (User::factory()->count(2)->create() as $index => $user) {
            Review::query()->create([
                'user_id' => $user->id,
                'reviewable_type' => Doctor::class,
                'reviewable_id' => $doctor->id,
                'rating' => $index === 0 ? 5 : 4,
                'status' => ReviewStatus::Approved,
                'published_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/search?q=ana+reviewed')
            ->assertOk()
            ->assertJsonPath('data.doctors.data.0.review_summary.count', 2)
            ->assertJsonPath('data.doctors.data.0.review_summary.average_rating', 4.5)
            ->assertJsonPath('data.doctors.data.0.primary_facility.name', 'Visible Wing');
    }

    public function test_topics_in_unpublished_categories_are_not_returned(): void
    {
        $hidden = ForumCategory::factory()->create(['is_published' => false]);
        $published = ForumCategory::factory()->create(['is_published' => true]);
        ForumTopic::factory()->create(['forum_category_id' => $hidden->id, 'title' => 'Ana hidden topic']);
        ForumTopic::factory()->create(['forum_category_id' => $published->id, 'title' => 'Ana visible topic']);

        $this->getJson('/api/v1/search?q=ana')
            ->assertOk()
            ->assertJsonPath('data.forum_topics.meta.total', 1)
            ->assertJsonPath('data.forum_topics.data.0.title', 'Ana visible topic');

        $this->getJson('/api/v1/forum/topics?q=ana')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Ana visible topic');

        $this->assertStringContainsString(
            'category_is_published=true',
            $this->engine->lastSearch('forum_topics')['params']['filter'] ?? '',
        );
    }

    public function test_toggling_category_publication_resyncs_its_topics_in_the_index(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'title' => 'Ana toggled']);

        $this->assertArrayHasKey($topic->id, $this->engine->indexes['forum_topics'] ?? []);

        $category->update(['is_published' => false]);
        $this->assertArrayNotHasKey($topic->id, $this->engine->indexes['forum_topics'] ?? []);

        $category->update(['is_published' => true]);
        $this->assertArrayHasKey($topic->id, $this->engine->indexes['forum_topics'] ?? []);
    }

    public function test_stale_index_entries_are_rechecked_against_the_database(): void
    {
        // Query-builder updates (and queued Scout syncs that have not run yet)
        // bypass the model observers, so the index still holds these rows.
        $category = ForumCategory::factory()->create(['is_published' => true]);
        $doctor = Doctor::factory()->create(['full_name' => 'Ana Stale Doctor']);
        Doctor::factory()->create(['full_name' => 'Ana Live Doctor']);
        $clinic = Facility::factory()->create(['name' => 'Ana Stale Clinic', 'type' => FacilityType::Clinic]);
        Facility::factory()->create(['name' => 'Ana Live Clinic', 'type' => FacilityType::Clinic]);
        $pharmacy = Facility::factory()->create(['name' => 'Ana Stale Pharmacy', 'type' => FacilityType::Pharmacy]);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'title' => 'Ana stale topic']);
        ForumTopic::factory()->create(['forum_category_id' => $category->id, 'title' => 'Ana live topic']);

        Doctor::query()->whereKey($doctor->id)->update(['is_published' => false]);
        Facility::query()->whereKey([$clinic->id, $pharmacy->id])->update(['is_published' => false]);
        ForumTopic::query()->whereKey($topic->id)->update(['status' => ForumContentStatus::Pending]);

        $this->assertArrayHasKey($doctor->id, $this->engine->indexes['doctors']);

        $this->getJson('/api/v1/search?q=ana&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 1)
            ->assertJsonPath('data.doctors.data.0.full_name', 'Ana Live Doctor')
            ->assertJsonPath('data.facilities.meta.total', 1)
            ->assertJsonPath('data.facilities.data.0.name', 'Ana Live Clinic')
            ->assertJsonPath('data.pharmacies.meta.total', 0)
            ->assertJsonPath('data.forum_topics.meta.total', 1)
            ->assertJsonPath('data.forum_topics.data.0.title', 'Ana live topic')
            ->assertJsonPath('data.grand_total', 3);

        $this->getJson('/api/v1/forum/topics?q=ana')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Ana live topic');

        // The category itself unpublished behind the observers' back.
        ForumCategory::query()->whereKey($category->id)->update(['is_published' => false]);

        $this->getJson('/api/v1/search?q=ana')
            ->assertOk()
            ->assertJsonPath('data.forum_topics.meta.total', 0);
        $this->getJson('/api/v1/forum/topics?q=ana')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_stale_entries_do_not_leave_totals_out_of_step_across_pages(): void
    {
        $stale = collect(range(1, 3))->map(
            fn (int $i) => Doctor::factory()->create(['full_name' => "Ana Stale {$i}"]),
        );
        foreach (range(1, 4) as $i) {
            Doctor::factory()->create(['full_name' => "Ana Live {$i}"]);
        }
        Doctor::query()->whereKey($stale->pluck('id')->all())->update(['is_published' => false]);

        $this->getJson('/api/v1/search?q=ana&per_page=3')
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 4)
            ->assertJsonPath('data.doctors.meta.last_page', 2);
    }

    /**
     * The visibility guard (->query()) makes Scout recount the total with a
     * second, unpaginated search of up to maxTotalHits hits. Only ids are
     * needed — for the page and for the recount — so nothing else, forum topic
     * bodies included, may come back over the wire.
     */
    public function test_searches_and_their_recounts_retrieve_ids_only(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => true]);
        foreach (range(1, 3) as $i) {
            Doctor::factory()->create(['full_name' => "Ana Doctor {$i}"]);
            Facility::factory()->create(['name' => "Ana Clinic {$i}", 'type' => FacilityType::Clinic]);
            Facility::factory()->create(['name' => "Ana Pharmacy {$i}", 'type' => FacilityType::Pharmacy]);
            ForumTopic::factory()->create(['forum_category_id' => $category->id, 'title' => "Ana topic {$i}"]);
        }

        $this->getJson('/api/v1/search?q=ana&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 3)
            ->assertJsonPath('data.doctors.data.0.full_name', 'Ana Doctor 1')
            ->assertJsonPath('data.forum_topics.meta.total', 3);
        $this->getJson('/api/v1/forum/topics?q=ana&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.title', 'Ana topic 1');

        $byIndex = collect($this->engine->searches)->groupBy('index');

        // Page + recount for each vertical (two facility verticals, two forum callers).
        $this->assertCount(2, $byIndex['doctors']);
        $this->assertCount(4, $byIndex['facilities']);
        $this->assertCount(4, $byIndex['forum_topics']);

        foreach ($this->engine->searches as $search) {
            $this->assertSame(
                ['id'],
                array_values(array_unique($search['params']['attributesToRetrieve'] ?? ['*'])),
                "A search on [{$search['index']}] retrieved whole documents.",
            );
        }
    }

    public function test_moving_a_topic_indexes_the_new_categorys_visibility(): void
    {
        $published = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'open']);
        $hidden = ForumCategory::factory()->create(['is_published' => false, 'slug' => 'closed']);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $published->id, 'title' => 'Ana moved']);
        $topic->load('category');

        $topic->update(['forum_category_id' => $hidden->id]);
        $this->assertArrayNotHasKey($topic->id, $this->engine->indexes['forum_topics'] ?? []);

        $other = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'other']);
        $topic->update(['forum_category_id' => $other->id]);
        $this->assertSame('other', $this->engine->indexes['forum_topics'][$topic->id]['category_slug'] ?? null);
    }

    public function test_republishing_a_category_resyncs_its_topics_without_a_query_per_topic(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => false]);
        ForumTopic::factory()->count(5)->create(['forum_category_id' => $category->id]);

        DB::enableQueryLog();
        $category->update(['is_published' => true]);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(5, $this->engine->indexes['forum_topics'] ?? []);
        $this->assertLessThanOrEqual(
            1,
            $queries->filter(fn (string $sql): bool => str_contains($sql, 'from "forum_categories"'))->count(),
            "Categories were loaded once per topic:\n".$queries->implode("\n"),
        );
    }

    public function test_city_values_are_escaped_the_way_the_meilisearch_filter_parser_unescapes(): void
    {
        Doctor::factory()->create(['full_name' => 'Ana Quoted', 'city' => 'Скопје "Центар"']);
        Doctor::factory()->create(['full_name' => 'Ana Slashed', 'city' => 'Скопје \\ Аеродром']);

        $this->getJson('/api/v1/search?q=ana&city='.rawurlencode('Скопје'))
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 1)
            ->assertJsonPath('data.doctors.data.0.full_name', 'Ana Quoted');

        $this->assertSame(
            'city IN ["Скопје \\"Центар\\""]',
            $this->engine->lastSearch('doctors')['params']['filter'] ?? null,
        );
    }

    public function test_doctors_and_facilities_match_specialty_and_department_names_in_either_script(): void
    {
        $cardiology = Specialty::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);
        $department = Department::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);

        $doctor = Doctor::factory()->create(['slug' => 'cardio-doc', 'full_name' => 'д-р Ана Петровска']);
        $doctor->specialties()->attach($cardiology->id, ['is_primary' => true]);
        $hospital = Facility::factory()->create(['slug' => 'city-hospital', 'name' => 'Градска болница', 'type' => FacilityType::Hospital]);
        $hospital->departments()->attach($department->id);
        Doctor::factory()->create(['slug' => 'other-doc', 'full_name' => 'д-р Марко Стојанов']);

        // Attaching a relation fires no model event; reindex as search:reindex would.
        $doctor->refresh()->searchable();
        $hospital->refresh()->searchable();

        foreach (['кардио', 'kardio'] as $q) {
            $this->getJson('/api/v1/search?q='.urlencode($q))
                ->assertOk()
                ->assertJsonPath('data.doctors.meta.total', 1)
                ->assertJsonPath('data.doctors.data.0.slug', 'cardio-doc')
                ->assertJsonPath('data.facilities.meta.total', 1)
                ->assertJsonPath('data.facilities.data.0.slug', 'city-hospital');
        }
    }

    /**
     * Doctor documents embed specialty names, and Scout re-indexes a doctor
     * only when the doctor is saved: a renamed, unpublished or deleted
     * specialty used to stay searchable under its old name.
     */
    public function test_specialty_changes_reindex_their_doctors(): void
    {
        $cardiology = Specialty::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);
        $doctors = Doctor::factory()->count(3)->create();
        $outsider = Doctor::factory()->create();
        foreach ($doctors as $doctor) {
            $doctor->specialties()->attach($cardiology->id, ['is_primary' => true]);
            $doctor->refresh()->searchable();
        }
        $outsider->refresh()->searchable();

        $names = fn (Doctor $doctor): array => $this->engine->indexes['doctors'][$doctor->id]['specialty_names'];

        $cardiology->update(['name' => 'Кардиохирургија']);
        foreach ($doctors as $doctor) {
            $this->assertSame(['Кардиохирургија'], $names($doctor));
        }
        $this->assertSame([], $names($outsider));

        $cardiology->update(['is_published' => false]);
        $this->assertSame([], $names($doctors[0]));

        $cardiology->update(['is_published' => true]);
        $this->assertSame(['Кардиохирургија'], $names($doctors[0]));

        $cardiology->delete();
        $this->assertSame([], $names($doctors[0]));

        $cardiology->restore();
        $this->assertSame(['Кардиохирургија'], $names($doctors[0]));
    }

    public function test_department_changes_reindex_their_facilities(): void
    {
        $department = Department::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);
        $hospital = Facility::factory()->create(['type' => FacilityType::Hospital]);
        $hospital->departments()->attach($department->id);
        $hospital->refresh()->searchable();

        $names = fn (): array => $this->engine->indexes['facilities'][$hospital->id]['department_names'];

        $department->update(['name' => 'Ортопедија']);
        $this->assertSame(['Ортопедија'], $names());

        $department->update(['is_published' => false]);
        $this->assertSame([], $names());

        $department->update(['is_published' => true]);
        $department->delete();
        $this->assertSame([], $names());
    }

    public function test_taxonomy_reindexing_is_queued_and_skips_unrelated_edits(): void
    {
        $specialty = Specialty::factory()->create();
        $department = Department::factory()->create();

        Queue::fake();

        $specialty->update(['sort_order' => 9, 'description' => 'x']);
        $department->update(['sort_order' => 9]);
        Queue::assertNothingPushed();

        $specialty->update(['name' => 'Ново име']);
        $department->delete();

        Queue::assertPushed(ReindexTaxonomyMembers::class, 2);
        Queue::assertPushed(
            ReindexTaxonomyMembers::class,
            fn (ReindexTaxonomyMembers $job): bool => $job->taxonomy === Specialty::class && $job->taxonomyId === $specialty->id,
        );
    }
}
