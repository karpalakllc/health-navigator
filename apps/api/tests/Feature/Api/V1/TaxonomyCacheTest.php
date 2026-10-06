<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Taxonomy GETs are cached server side (TaxonomyCache) and marked cacheable
 * for browsers / the web tier with an ETag (cache.public).
 */
class TaxonomyCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Specialty::factory()->create(['slug' => 'kardiologija', 'name' => 'Кардиологија', 'is_published' => true]);
        Department::factory()->create(['slug' => 'urgenten', 'name' => 'Ургентен центар', 'is_published' => true]);
        ForumCategory::factory()->create(['slug' => 'general', 'name' => 'Општо', 'is_published' => true]);
    }

    /** @return array<string, array{string}> */
    public static function taxonomyEndpoints(): array
    {
        return [
            'specialties' => ['/api/v1/specialties'],
            'specialty' => ['/api/v1/specialties/kardiologija'],
            'departments' => ['/api/v1/departments'],
            'forum categories' => ['/api/v1/forum/categories'],
        ];
    }

    #[DataProvider('taxonomyEndpoints')]
    public function test_taxonomy_responses_are_publicly_cacheable_and_revalidate_with_304(string $uri): void
    {
        $response = $this->getJson($uri)->assertOk();

        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));

        $notModified = $this->getJson($uri, ['If-None-Match' => $etag]);
        $notModified->assertStatus(304);
        $this->assertSame('', $notModified->getContent());

        $this->getJson($uri, ['If-None-Match' => '"stale"'])->assertOk();
    }

    #[DataProvider('taxonomyEndpoints')]
    public function test_a_repeat_request_is_served_from_the_cache(string $uri): void
    {
        $this->getJson($uri)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $tables = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        foreach (['specialties', 'departments', 'forum_categories'] as $table) {
            $this->assertStringNotContainsString('"'.$table.'"', $tables);
        }
    }

    public function test_missing_specialty_is_not_marked_publicly_cacheable(): void
    {
        $response = $this->getJson('/api/v1/specialties/missing')->assertNotFound();

        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('ETag'));
    }

    public function test_editing_a_specialty_busts_the_list_and_detail(): void
    {
        $before = $this->getJson('/api/v1/specialties')->assertOk();
        $this->getJson('/api/v1/specialties/kardiologija')->assertJsonPath('data.name', 'Кардиологија');

        Specialty::query()->where('slug', 'kardiologija')->firstOrFail()->update(['name' => 'Кардиологија и ангиологија']);

        $after = $this->getJson('/api/v1/specialties', ['If-None-Match' => $before->headers->get('ETag')]);
        $after->assertOk()->assertJsonPath('data.0.name', 'Кардиологија и ангиологија');
        $this->getJson('/api/v1/specialties/kardiologija')->assertJsonPath('data.name', 'Кардиологија и ангиологија');
    }

    public function test_publishing_a_doctor_refreshes_the_specialty_doctor_count(): void
    {
        $doctor = Doctor::factory()->unpublished()->create();
        $doctor->specialties()->attach(Specialty::query()->value('id'), ['is_primary' => true]);

        $this->getJson('/api/v1/specialties')->assertJsonPath('data.0.doctors_count', 0);

        $doctor->update(['is_published' => true]);

        $this->getJson('/api/v1/specialties')->assertJsonPath('data.0.doctors_count', 1);
    }

    public function test_deleting_and_restoring_a_department_busts_the_list(): void
    {
        $this->getJson('/api/v1/departments')->assertJsonCount(1, 'data');

        $department = Department::query()->firstOrFail();
        $department->delete();
        $this->getJson('/api/v1/departments')->assertJsonCount(0, 'data');

        $department->restore();
        $this->getJson('/api/v1/departments')->assertJsonCount(1, 'data');
    }

    public function test_approving_a_topic_refreshes_the_category_topic_count(): void
    {
        $topic = ForumTopic::factory()->pending()->create([
            'forum_category_id' => ForumCategory::query()->value('id'),
        ]);

        $this->getJson('/api/v1/forum/categories')->assertJsonPath('data.0.topics_count', 0);

        $topic->approve(User::factory()->moderator()->create());

        $this->getJson('/api/v1/forum/categories')->assertJsonPath('data.0.topics_count', 1);
    }
}
