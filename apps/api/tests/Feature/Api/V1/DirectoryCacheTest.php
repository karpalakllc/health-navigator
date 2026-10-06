<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Anonymous directory and product reads are marked `public, max-age=60` with a
 * content ETag (cache.public:60) and revalidate with a 304. A request that
 * carries credentials is never marked, and the review lists, which hold the
 * viewer's own review, stay private.
 */
class DirectoryCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $doctor = Doctor::factory()->create(['slug' => 'dr-a', 'is_published' => true]);
        $clinic = Facility::factory()->create(['slug' => 'clinic-a', 'type' => FacilityType::Clinic]);
        $doctor->facilities()->attach($clinic->id, ['is_primary' => true]);

        $pharmacy = Facility::factory()->pharmacy()->create(['slug' => 'pharmacy-a']);
        $product = Product::factory()->create(['slug' => 'product-a']);
        $pharmacy->products()->attach($product->id, [
            'price' => 100,
            'currency' => 'MKD',
            'is_available' => true,
            'price_updated_at' => now(),
        ]);
    }

    /** @return array<string, array{string}> */
    public static function cacheableEndpoints(): array
    {
        return [
            'doctors' => ['/api/v1/doctors'],
            'doctors filtered' => ['/api/v1/doctors?sort=rating&page=1'],
            'doctor' => ['/api/v1/doctors/dr-a'],
            'facilities' => ['/api/v1/facilities'],
            'facility' => ['/api/v1/facilities/clinic-a'],
            'pharmacies' => ['/api/v1/pharmacies'],
            'pharmacy' => ['/api/v1/pharmacies/pharmacy-a'],
            'pharmacy shelf' => ['/api/v1/pharmacies/pharmacy-a/products'],
            'products' => ['/api/v1/products'],
            'product' => ['/api/v1/products/product-a'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function viewerSpecificEndpoints(): array
    {
        return [
            'doctor reviews' => ['/api/v1/doctors/dr-a/reviews'],
            'facility reviews' => ['/api/v1/facilities/clinic-a/reviews'],
            'pharmacy reviews' => ['/api/v1/pharmacies/pharmacy-a/reviews'],
        ];
    }

    private function assertNotPubliclyCacheable(TestResponse $response): void
    {
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('ETag'));
    }

    #[DataProvider('cacheableEndpoints')]
    public function test_anonymous_reads_are_public_for_a_minute_and_revalidate_with_304(string $uri): void
    {
        $response = $this->getJson($uri)->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);

        $vary = implode(',', $response->headers->all('Vary'));
        $this->assertStringContainsString('Authorization', $vary);
        $this->assertStringContainsString('Accept-Language', $vary);

        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $notModified = $this->getJson($uri, ['If-None-Match' => $etag]);
        $notModified->assertStatus(304);
        $this->assertSame('', $notModified->getContent());
        $this->assertSame($etag, $notModified->headers->get('ETag'));

        $this->getJson($uri, ['If-None-Match' => '"stale"'])->assertOk();
    }

    #[DataProvider('cacheableEndpoints')]
    public function test_a_request_with_a_bearer_token_is_never_marked_cacheable(string $uri): void
    {
        $etag = $this->getJson($uri)->assertOk()->headers->get('ETag');
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $response = $this->getJson($uri, [
            'Authorization' => 'Bearer '.$token,
            'If-None-Match' => (string) $etag,
        ]);

        $response->assertOk();
        $this->assertNotPubliclyCacheable($response);
        $this->assertNotSame('', $response->getContent());
    }

    public function test_an_invalid_bearer_token_is_not_marked_cacheable_either(): void
    {
        $response = $this->getJson('/api/v1/doctors', ['Authorization' => 'Bearer not-a-token'])->assertOk();

        $this->assertNotPubliclyCacheable($response);
    }

    #[DataProvider('viewerSpecificEndpoints')]
    public function test_review_lists_carrying_viewer_state_are_not_cached(string $uri): void
    {
        $this->assertNotPubliclyCacheable($this->getJson($uri)->assertOk());
    }

    public function test_missing_profiles_are_not_marked_cacheable(): void
    {
        foreach (['/api/v1/doctors/missing', '/api/v1/facilities/missing', '/api/v1/products/missing'] as $uri) {
            $this->assertNotPubliclyCacheable($this->getJson($uri)->assertNotFound());
        }
    }

    public function test_validation_errors_are_not_marked_cacheable(): void
    {
        $this->assertNotPubliclyCacheable($this->getJson('/api/v1/doctors?sort=bogus')->assertUnprocessable());
    }

    public function test_switched_off_modules_are_not_marked_cacheable(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => false, 'public_products' => false]);

        foreach (['/api/v1/pharmacies', '/api/v1/pharmacies/pharmacy-a', '/api/v1/products'] as $uri) {
            $this->assertNotPubliclyCacheable($this->getJson($uri)->assertStatus(503));
        }
    }

    public function test_an_admin_edit_changes_the_etag_so_revalidation_returns_the_new_payload(): void
    {
        $before = $this->getJson('/api/v1/doctors/dr-a')->assertOk();

        Doctor::query()->where('slug', 'dr-a')->firstOrFail()->update(['full_name' => 'д-р Нов Назив']);

        $this->getJson('/api/v1/doctors/dr-a', ['If-None-Match' => (string) $before->headers->get('ETag')])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'д-р Нов Назив');
    }

    public function test_search_stays_uncached_because_it_records_anonymous_aggregates(): void
    {
        $this->assertNotPubliclyCacheable($this->getJson('/api/v1/search?q=dr')->assertOk());
    }
}
