<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * „Верификуван“ / „Неверификуван“ in the public API: every doctor,
 * facility and pharmacy item carries `verification` (status + public basis
 * label, never the evidence), and the lists filter with `verified=1`.
 */
class VerificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    private const EVIDENCE = 'internal-evidence-marker';

    private function verify(Doctor|Facility $subject, VerificationBasis $basis = VerificationBasis::OfficialRegisters): void
    {
        app(VerificationWriter::class)->verify($subject, $basis, [['note' => self::EVIDENCE]], null, self::EVIDENCE);
    }

    /**
     * @return list<string>
     */
    private function slugs(string $uri): array
    {
        return array_column($this->getJson($uri)->assertOk()->json('data'), 'slug');
    }

    public function test_doctor_list_and_detail_carry_the_public_verification_only(): void
    {
        $verified = Doctor::factory()->create(['slug' => 'alpha', 'full_name' => 'Alpha']);
        Doctor::factory()->create(['slug' => 'bravo', 'full_name' => 'Bravo']);
        $this->verify($verified);

        $list = $this->withHeader('Accept-Language', 'mk')->getJson('/api/v1/doctors')->assertOk();
        $list->assertJsonPath('data.0.verification', [
            'status' => 'verified',
            'basis' => 'official_registers',
            'basis_label' => 'Регистар на ФЗОМ и Лекарска комора',
        ]);
        $list->assertJsonPath('data.1.verification', ['status' => 'unverified', 'basis' => null, 'basis_label' => null]);

        $detail = $this->getJson('/api/v1/doctors/alpha')->assertOk();
        $detail->assertJsonPath('data.verification.status', 'verified');

        foreach ([$list, $detail] as $response) {
            $this->assertStringNotContainsString(self::EVIDENCE, (string) $response->getContent());
            $this->assertStringNotContainsString('verification_reasons', (string) $response->getContent());
        }
    }

    public function test_only_verified_filters_doctors_facilities_and_pharmacies(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => true]);

        $this->verify(Doctor::factory()->create(['slug' => 'doc-yes', 'full_name' => 'Alpha']));
        Doctor::factory()->create(['slug' => 'doc-no', 'full_name' => 'Bravo']);
        $this->verify(Facility::factory()->create(['slug' => 'clinic-yes', 'name' => 'Alpha']));
        Facility::factory()->create(['slug' => 'clinic-no', 'name' => 'Bravo']);
        $this->verify(Facility::factory()->pharmacy()->create(['slug' => 'pharmacy-yes', 'name' => 'Alpha']));
        Facility::factory()->pharmacy()->create(['slug' => 'pharmacy-no', 'name' => 'Bravo']);

        $this->assertSame(['doc-yes', 'doc-no'], $this->slugs('/api/v1/doctors'));
        $this->assertSame(['doc-yes'], $this->slugs('/api/v1/doctors?verified=1'));
        $this->assertSame(['clinic-yes'], $this->slugs('/api/v1/facilities?verified=1'));
        $this->assertSame(['pharmacy-yes'], $this->slugs('/api/v1/pharmacies?verified=1'));
        $this->assertSame(['doc-yes', 'doc-no'], $this->slugs('/api/v1/doctors?verified=0'));

        $this->getJson('/api/v1/pharmacies/pharmacy-yes')->assertOk()
            ->assertJsonPath('data.verification.status', 'verified');
        $this->getJson('/api/v1/facilities/clinic-no')->assertOk()
            ->assertJsonPath('data.verification.status', 'unverified');
        $this->getJson('/api/v1/doctors?verified=maybe')->assertStatus(422);
    }
}
