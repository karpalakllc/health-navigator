<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_approved_reviews_for_published_doctor(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Review::factory()->approved()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'rating' => 5,
            'body' => 'Great experience overall.',
        ]);

        Review::factory()->create([
            'user_id' => User::factory(),
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'status' => ReviewStatus::Pending,
        ]);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.author_name', $member->name)
            ->assertJsonStructure(['meta' => ['current_page', 'per_page', 'total', 'last_page']]);
    }

    public function test_doctor_detail_includes_review_summary(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);

        Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'rating' => 4,
        ]);
        Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'rating' => 2,
        ]);

        $this->getJson('/api/v1/doctors/ana-petrovska')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 2)
            ->assertJsonPath('data.review_summary.average_rating', 3);
    }

    public function test_member_can_submit_review_for_doctor(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', [
            'rating' => 5,
            'body' => 'Excellent care and attention.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'status' => ReviewStatus::Pending->value,
        ]);
    }

    public function test_duplicate_review_returns_422(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Review::factory()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', [
            'rating' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review']);
    }

    public function test_staff_cannot_submit_review(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Moderator]));

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5])
            ->assertForbidden();
    }

    public function test_submit_to_unpublished_doctor_returns_404(): void
    {
        Doctor::factory()->unpublished()->create(['slug' => 'hidden-doc']);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Member]));

        $this->postJson('/api/v1/doctors/hidden-doc/reviews', ['rating' => 5])
            ->assertNotFound();
    }

    public function test_member_can_list_own_reviews(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);

        Review::factory()->approved()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/me/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.reviewable.slug', 'ana-petrovska');
    }

    public function test_facility_reviews_and_summary(): void
    {
        $facility = Facility::factory()->create(['slug' => 'klinika-ana']);

        Review::factory()->approved()->create([
            'reviewable_type' => Facility::class,
            'reviewable_id' => $facility->id,
            'rating' => 5,
        ]);

        $this->getJson('/api/v1/facilities/klinika-ana/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/facilities/klinika-ana')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 1)
            ->assertJsonPath('data.review_summary.average_rating', 5);
    }

    public function test_validates_review_payload(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Member]));

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 6])
            ->assertUnprocessable();

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', [
            'rating' => 5,
            'body' => 'short',
        ])->assertUnprocessable();
    }
}
