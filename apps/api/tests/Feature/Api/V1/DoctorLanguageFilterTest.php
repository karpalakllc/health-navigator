<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /doctors?language= filters on the doctor_language taxonomy, and
 * GET /languages lists the choices the filter offers.
 */
class DoctorLanguageFilterTest extends TestCase
{
    use RefreshDatabase;

    private Language $english;

    private Language $albanian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->english = Language::factory()->create(['name' => 'Англиски', 'slug' => 'angliski', 'sort_order' => 2]);
        $this->albanian = Language::factory()->create(['name' => 'Албански', 'slug' => 'albanski', 'sort_order' => 1]);
    }

    private function doctor(string $name, array $languages, array $attributes = []): Doctor
    {
        $doctor = Doctor::factory()->create(['full_name' => $name, ...$attributes]);
        $doctor->languages()->attach(array_map(fn (Language $language) => $language->id, $languages));

        return $doctor;
    }

    public function test_filters_doctors_by_language_slug(): void
    {
        $this->doctor('д-р Ана', [$this->english]);
        $this->doctor('д-р Бојан', [$this->english, $this->albanian]);
        $this->doctor('д-р Весна', [$this->albanian]);
        $this->doctor('д-р Горан', []);

        $this->getJson('/api/v1/doctors?language=angliski')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.*.full_name', ['д-р Ана', 'д-р Бојан']);

        $this->getJson('/api/v1/doctors?language=albanski')
            ->assertOk()
            ->assertJsonPath('data.*.full_name', ['д-р Бојан', 'д-р Весна']);
    }

    public function test_language_combines_with_other_filters_and_keeps_featured_first(): void
    {
        $this->doctor('д-р Ана', [$this->english], ['city' => 'Скопје']);
        $this->doctor('д-р Бојан', [$this->english], ['city' => 'Скопје', 'is_featured' => true]);
        $this->doctor('д-р Весна', [$this->english], ['city' => 'Битола']);

        $this->getJson('/api/v1/doctors?language=angliski&city='.rawurlencode('Скопје'))
            ->assertOk()
            ->assertJsonPath('data.*.full_name', ['д-р Бојан', 'д-р Ана']);
    }

    public function test_unknown_unpublished_or_deleted_languages_are_rejected(): void
    {
        $hidden = Language::factory()->create(['slug' => 'skrien', 'is_published' => false]);
        $deleted = Language::factory()->create(['slug' => 'izbrishan']);
        $deleted->delete();

        foreach (['nepostoi', $hidden->slug, 'izbrishan'] as $slug) {
            $this->getJson('/api/v1/doctors?language='.$slug)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('language');
        }
    }

    public function test_an_unpublished_doctor_is_not_returned_for_their_language(): void
    {
        $this->doctor('д-р Скриен', [$this->english], ['is_published' => false]);

        $this->getJson('/api/v1/doctors?language=angliski')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_languages_endpoint_lists_published_languages_spoken_by_published_doctors(): void
    {
        $this->doctor('д-р Ана', [$this->english, $this->albanian]);
        $this->doctor('д-р Бојан', [$this->albanian]);
        $this->doctor('д-р Скриен', [Language::factory()->create(['slug' => 'samo-skrien'])], ['is_published' => false]);
        Language::factory()->create(['slug' => 'bez-lekari']);
        $unpublished = Language::factory()->create(['slug' => 'neobjaven', 'is_published' => false]);
        $this->doctor('д-р Весна', [$unpublished]);

        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['slug' => 'albanski', 'name' => 'Албански', 'doctors_count' => 2],
                ['slug' => 'angliski', 'name' => 'Англиски', 'doctors_count' => 1],
            ]]);
    }

    public function test_languages_endpoint_is_publicly_cacheable(): void
    {
        $this->doctor('д-р Ана', [$this->english]);

        $response = $this->getJson('/api/v1/languages')->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->getJson('/api/v1/languages', ['If-None-Match' => (string) $response->headers->get('ETag')])
            ->assertStatus(304);
    }

    public function test_publishing_a_doctor_or_editing_a_language_refreshes_the_cached_list(): void
    {
        $doctor = $this->doctor('д-р Ана', [$this->english], ['is_published' => false]);

        $this->getJson('/api/v1/languages')->assertJsonCount(0, 'data');

        $doctor->update(['is_published' => true]);
        $this->getJson('/api/v1/languages')->assertJsonPath('data.0.slug', 'angliski');

        $this->english->update(['name' => 'Англиски јазик']);
        $this->getJson('/api/v1/languages')->assertJsonPath('data.0.name', 'Англиски јазик');
    }
}
