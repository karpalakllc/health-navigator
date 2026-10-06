<?php

namespace Tests\Feature\Import;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Specialty;
use App\Support\Import\Fzom\FzomImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Imported profiles are hidden drafts until staff publish them. Every public
 * surface reads through the API — the web app's sitemap, llms.txt and the
 * JSON-LD on profile pages are built from these endpoints — so none of them
 * may name an imported draft, its name, its slug or the facsimile key.
 */
class ImportedDraftsStayHiddenTest extends TestCase
{
    use RefreshDatabase;

    private const DRAFT_NAMES = ['Првана', 'Примеровска', 'Срцевски', 'Забаров', 'Ретковски', 'Тест Медика', 'Забко Дент', 'Општа Болница Тестово', 'Здравствен Дом Тестово'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);

        app(FzomImportJob::class)->run(false, null, [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);
    }

    private function assertLeaksNothing(TestResponse $response, string $what): void
    {
        $response->assertOk();
        $body = (string) $response->getContent();
        $decoded = json_decode($body, true);
        $text = $decoded !== null ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : $body;

        foreach (self::DRAFT_NAMES as $name) {
            $this->assertStringNotContainsString($name, (string) $text, "{$what} names the draft \"{$name}\".");
        }

        foreach (Doctor::query()->where('is_published', false)->pluck('slug')->merge(Facility::query()->where('is_published', false)->pluck('slug')) as $slug) {
            $this->assertStringNotContainsString('"'.$slug.'"', (string) $text, "{$what} links the draft {$slug}.");
        }

        $this->assertStringNotContainsString('900010', (string) $text, "{$what} exposes a facsimile number.");
    }

    public function test_no_public_endpoint_lists_or_counts_imported_drafts(): void
    {
        // A published doctor and facility next to the drafts, linked to them,
        // so the relation filters are exercised too.
        $hospital = Facility::query()->where('fzo_code', '9000010')->firstOrFail();
        $publicFacility = Facility::factory()->create(['name' => 'Јавна установа', 'city' => 'Тестово', 'type' => 'hospital']);
        $publicDoctor = Doctor::factory()->create(['full_name' => 'Јавен Лекар', 'city' => 'Тестово']);
        $publicDoctor->facilities()->attach([$hospital->getKey() => ['is_primary' => true], $publicFacility->getKey() => ['is_primary' => false]]);
        $kardiologija = Specialty::query()->where('slug', 'kardiologija')->firstOrFail();
        $kardiologija->forceFill(['is_published' => true])->save();
        $publicDoctor->specialties()->attach($kardiologija->getKey(), ['is_primary' => true]);
        $draft = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $draft->facilities()->syncWithoutDetaching([$publicFacility->getKey()]);

        $this->assertLeaksNothing($this->getJson('/api/v1/doctors?per_page=50'), 'GET /doctors');
        $this->assertLeaksNothing($this->getJson('/api/v1/doctors?q='.rawurlencode('Срцевски')), 'GET /doctors?q=');
        $this->assertLeaksNothing($this->getJson('/api/v1/doctors?city='.rawurlencode('Тестово')), 'GET /doctors?city=');
        $this->assertLeaksNothing($this->getJson('/api/v1/doctors?specialty=kardiologija'), 'GET /doctors?specialty=');
        $this->assertLeaksNothing($this->getJson('/api/v1/doctors/'.$publicDoctor->slug), 'GET /doctors/{public}');
        $this->assertLeaksNothing($this->getJson('/api/v1/facilities?per_page=50'), 'GET /facilities');
        $this->assertLeaksNothing($this->getJson('/api/v1/facilities/'.$publicFacility->slug), 'GET /facilities/{public}');
        $this->assertLeaksNothing($this->getJson('/api/v1/search?q='.rawurlencode('Тест')), 'GET /search');
        $this->assertLeaksNothing($this->getJson('/api/v1/search?q='.rawurlencode('Срцевски')), 'GET /search (name)');
        $this->assertLeaksNothing($this->getJson('/api/v1/home/highlights'), 'GET /home/highlights');
        $this->assertLeaksNothing($this->getJson('/api/v1/locations/cities'), 'GET /locations/cities');
        $this->assertLeaksNothing($this->getJson('/api/v1/specialties'), 'GET /specialties');
        $this->assertLeaksNothing($this->getJson('/api/v1/forum/topics/related?doctor='.$publicDoctor->slug), 'GET related topics');

        // Counts include published doctors only.
        $this->getJson('/api/v1/specialties/kardiologija')->assertOk()->assertJsonPath('data.doctors_count', 1);
        // Unimported, hidden specialties do not appear at all.
        $this->getJson('/api/v1/specialties/opshta-medicina')->assertNotFound();

        foreach (Doctor::query()->where('is_published', false)->get() as $doctor) {
            $this->getJson('/api/v1/doctors/'.$doctor->slug)->assertNotFound();
            $this->getJson('/api/v1/doctors/'.$doctor->slug.'/reviews')->assertNotFound();
            $this->getJson('/api/v1/forum/topics/related?doctor='.$doctor->slug)->assertNotFound();
            $this->assertFalse($doctor->shouldBeSearchable(), 'Drafts never enter the search index.');
        }

        foreach (Facility::query()->where('is_published', false)->get() as $facility) {
            $this->getJson('/api/v1/facilities/'.$facility->slug)->assertNotFound();
            $this->assertFalse($facility->shouldBeSearchable());
        }
    }

    public function test_imports_never_publish_and_never_touch_publication(): void
    {
        $this->assertSame(0, Doctor::query()->where('is_published', true)->count());
        $this->assertSame(0, Facility::query()->where('is_published', true)->count());
        $this->assertSame(0, Doctor::query()->whereNotNull('published_at')->count());
        $this->assertSame(0, Specialty::query()->where('is_published', true)->count());
    }
}
