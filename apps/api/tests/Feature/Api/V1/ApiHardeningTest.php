<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_json_error_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_missing_doctor_returns_json_not_found(): void
    {
        $this->getJson('/api/v1/doctors/missing-doctor')
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('api-login');

        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'password',
            'role' => UserRole::Member,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests.');
    }

    public function test_review_submission_is_rate_limited(): void
    {
        RateLimiter::clear('api-reviews');

        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        for ($i = 0; $i < 10; $i++) {
            $slug = "doctor-{$i}";
            Doctor::factory()->create(['slug' => $slug]);

            $this->postJson("/api/v1/doctors/{$slug}/reviews", [
                'rating' => 5,
                'body' => 'Excellent care and attention.',
            ])->assertCreated();
        }

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', [
            'rating' => 4,
            'body' => 'Another review attempt here.',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests.');
    }

    public function test_short_search_query_is_ignored(): void
    {
        Doctor::factory()->create([
            'full_name' => 'Ana Petrovska',
            'slug' => 'ana-petrovska',
        ]);

        $this->getJson('/api/v1/doctors?q=a')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
