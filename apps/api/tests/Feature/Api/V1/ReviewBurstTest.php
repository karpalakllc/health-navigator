<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The light anti-manipulation rules: one review per account per profile and a
 * verified email to write one (both already enforced), and a staff-only signal
 * when a profile receives five or more reviews within 24 hours — a flag and a
 * filter, never an automatic action.
 */
class ReviewBurstTest extends TestCase
{
    use RefreshDatabase;

    private function reviewFor(Doctor $doctor, string $at): Review
    {
        $this->travelTo($at);

        return Review::factory()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);
    }

    public function test_five_reviews_within_24_hours_flag_the_burst_without_changing_anything_else(): void
    {
        $doctor = Doctor::factory()->create();
        $other = Doctor::factory()->create();
        $early = $this->reviewFor($doctor, '2026-10-01 08:00:00');
        $burst = collect(['2026-10-02 09:00:00', '2026-10-02 12:00:00', '2026-10-02 18:00:00', '2026-10-03 01:00:00'])
            ->map(fn (string $at) => $this->reviewFor($doctor, $at));
        $this->reviewFor($other, '2026-10-02 10:00:00');

        $this->assertNull($burst->last()->fresh()->burst_flagged_at, 'four in the window: no flag yet');

        $fifth = $this->reviewFor($doctor, '2026-10-03 07:00:00');

        $this->assertNull($early->fresh()->burst_flagged_at, 'more than 24 h before the fifth');
        foreach ([...$burst, $fifth] as $review) {
            $this->assertNotNull($review->fresh()->burst_flagged_at);
            $this->assertSame('pending', $review->fresh()->status->value, 'no automatic action');
        }
        $this->assertSame(5, Review::query()->whereNotNull('burst_flagged_at')->count());
    }

    public function test_staff_can_filter_the_reviews_list_to_bursts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $doctor = Doctor::factory()->create();
        $calm = $this->reviewFor($doctor, '2026-09-01 08:00:00');
        $flagged = collect(range(1, 5))->map(fn (int $hour) => $this->reviewFor($doctor, "2026-10-02 0{$hour}:00:00"));

        $moderator = User::factory()->create(['user_kind' => 'staff']);
        $moderator->syncRoles([RoleCatalog::MODERATOR]);
        $this->actingAs($moderator);

        Livewire::test(ListReviews::class)
            ->filterTable('burst')
            ->assertCanSeeTableRecords($flagged)
            ->assertCanNotSeeTableRecords([$calm]);
    }

    public function test_one_review_per_account_and_profile_and_a_verified_email_are_required(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->forgetRateLimits();
        Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);

        $unverified = User::factory()->unverified()->create();
        $unverified->assignRole('Member');
        $this->actingAs($unverified, 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5])
            ->assertForbidden();

        $member = User::factory()->create();
        $member->assignRole('Member');
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5])
            ->assertCreated();
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');

        $this->assertSame(1, Review::query()->count());
    }
}
