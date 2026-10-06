<?php

namespace Tests\Feature\Import;

use App\Actions\DoctorAccount\AssignDoctorOwner;
use App\Enums\UserKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Specialty;
use App\Models\User;
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
            $this->getJson('/api/v1/facilities/'.$facility->slug.'/reviews')->assertNotFound();
            $this->postJson('/api/v1/facilities/'.$facility->slug.'/corrections', ['field' => 'name', 'message' => 'Името е погрешно напишано.'])->assertNotFound();
            $this->assertFalse($facility->shouldBeSearchable());
        }
    }

    public function test_website_import_drafts_stay_hidden_too(): void
    {
        $dir = sys_get_temp_dir().'/hidden-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/institutions.json', (string) json_encode(['slice' => 'x', 'institutions' => [[
            'name_mk' => 'ЈЗУ Измислена Клиника Скриена', 'town' => 'Скриено', 'type' => 'clinic', 'website' => 'https://skrieno.invalid/',
            'workers' => [['full_name' => 'Невидлив Веб-Лекар', 'role' => 'physician', 'specialty' => 'Кардиологија', 'source_url' => 'https://skrieno.invalid/tim']],
        ]]], JSON_UNESCAPED_UNICODE));
        $this->artisan('import:institutions-json', ['path' => $dir.'/institutions.json'])->assertSuccessful();
        @unlink($dir.'/institutions.json');
        @rmdir($dir);

        $doctor = Doctor::query()->where('name_key', 'НЕВИДЛИВ ВЕБ ЛЕКАР')->orWhere('full_name', 'like', '%Невидлив%')->firstOrFail();
        $facility = Facility::query()->where('website', 'https://skrieno.invalid/')->firstOrFail();
        $this->assertFalse($doctor->is_published);
        $this->assertFalse($facility->is_published);

        $this->getJson('/api/v1/doctors/'.$doctor->slug)->assertNotFound();
        $this->getJson('/api/v1/facilities/'.$facility->slug)->assertNotFound();

        foreach (['/api/v1/doctors?q='.rawurlencode('Невидлив'), '/api/v1/facilities?q='.rawurlencode('Скриена'), '/api/v1/search?q='.rawurlencode('Невидлив'), '/api/v1/locations/cities'] as $url) {
            $body = (string) json_encode($this->getJson($url)->assertOk()->json(), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('Невидлив', $body, $url);
            $this->assertStringNotContainsString('Скрие', $body, $url);
        }
    }

    public function test_the_doctor_dashboard_offers_no_draft_facility(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $account = User::factory()->create(['user_kind' => UserKind::Client]);
        app(AssignDoctorOwner::class)->handle($doctor, $account, User::factory()->create(['user_kind' => UserKind::Staff]));
        $token = $account->createToken('web')->plainTextToken;

        $body = (string) json_encode($this->withToken($token)->getJson('/api/v1/me/doctor/facilities?q='.rawurlencode('Тест'))->assertOk()->json(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Тест Медика', $body);
        $this->assertStringNotContainsString('Тестово', $body);
    }

    public function test_imports_never_publish_and_never_touch_publication(): void
    {
        $this->assertSame(0, Doctor::query()->where('is_published', true)->count());
        $this->assertSame(0, Facility::query()->where('is_published', true)->count());
        $this->assertSame(0, Doctor::query()->whereNotNull('published_at')->count());
        $this->assertSame(0, Specialty::query()->where('is_published', true)->count());
    }
}
