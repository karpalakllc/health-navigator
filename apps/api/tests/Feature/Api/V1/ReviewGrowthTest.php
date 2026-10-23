<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ReviewBurstDetector;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * „First reviews“ (docs/reviews-growth.md): the admin switches, how the
 * public settings expose them, the staff-only „started from the shared
 * link“ mark and the shared-link burst signal.
 */
class ReviewGrowthTest extends TestCase
{
    use RefreshDatabase;

    private const FLAGS = [
        'reviews_prompt_on_profile',
        'reviews_prompt_after_search',
        'reviews_share_link',
        'reviews_mobile_sticky_cta',
    ];

    public function test_the_switches_are_on_by_default_and_exposed_only_as_booleans(): void
    {
        $data = $this->getJson('/api/v1/settings/public')->assertOk()->json('data');

        foreach (self::FLAGS as $flag) {
            $this->assertArrayHasKey($flag, $data);
            $this->assertTrue($data[$flag], "{$flag} defaults to on");
        }
    }

    public function test_switched_off_the_public_settings_say_so(): void
    {
        SiteSetting::current()->update(array_fill_keys(self::FLAGS, false));

        $data = $this->getJson('/api/v1/settings/public')->assertOk()->json('data');

        foreach (self::FLAGS as $flag) {
            $this->assertFalse($data[$flag], "{$flag} reads as off");
        }
    }

    public function test_an_administrator_switches_them_in_the_settings_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create(['user_kind' => 'staff']);
        $admin->syncRoles([RoleCatalog::ADMINISTRATOR]);
        $this->actingAs($admin);

        Livewire::test(ManageSiteSettings::class)
            ->assertSet('data.reviews_share_link', true)
            ->set('data.reviews_share_link', false)
            ->set('data.reviews_mobile_sticky_cta', false)
            ->call('save')
            ->assertHasNoErrors();

        SiteSetting::flushCache();
        $settings = SiteSetting::current();
        $this->assertFalse($settings->reviews_share_link);
        $this->assertFalse($settings->reviews_mobile_sticky_cta);
        $this->assertTrue($settings->reviews_prompt_on_profile);
        $this->assertTrue($settings->reviews_prompt_after_search);
    }

    public function test_a_review_from_the_shared_link_is_marked_for_staff_and_checked_like_any_other(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = $this->member();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5, 'via_shared_link' => true])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.via_shared_link');

        $review = Review::query()->sole();
        $this->assertTrue($review->via_shared_link);

        // Never in a public payload.
        $review->approve(User::factory()->create());
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonMissingPath('data.0.via_shared_link')
            ->assertJsonMissingPath('data.0.shared_link_burst_flagged_at');

        // The same one-per-profile rule.
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 1, 'via_shared_link' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
        $this->assertSame($doctor->id, $review->reviewable_id);
    }

    public function test_the_mark_is_ignored_when_the_share_link_is_off_or_the_profile_is_not_a_doctor(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);
        Facility::factory()->create(['slug' => 'klinika', 'type' => FacilityType::Clinic]);

        $this->actingAs($this->member(), 'sanctum')
            ->postJson('/api/v1/facilities/klinika/reviews', ['rating' => 4, 'via_shared_link' => true])
            ->assertCreated();

        SiteSetting::current()->update(['reviews_share_link' => false]);

        $this->actingAs($this->member(), 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 4, 'via_shared_link' => true])
            ->assertCreated();

        $this->assertSame(0, Review::query()->where('via_shared_link', true)->count());
    }

    public function test_a_non_boolean_mark_is_refused(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);

        $this->actingAs($this->member(), 'sanctum')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 4, 'via_shared_link' => 'sender-42'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('via_shared_link');
    }

    public function test_three_shared_link_reviews_within_48_hours_flag_them_without_any_automatic_action(): void
    {
        $doctor = Doctor::factory()->create();
        $other = Doctor::factory()->create();

        $early = $this->reviewFor($doctor, '2026-10-01 08:00:00', viaLink: true);
        $plain = $this->reviewFor($doctor, '2026-10-02 09:00:00', viaLink: false);
        $first = $this->reviewFor($doctor, '2026-10-03 10:00:00', viaLink: true);
        $this->reviewFor($other, '2026-10-03 11:00:00', viaLink: true);
        $second = $this->reviewFor($doctor, '2026-10-04 12:00:00', viaLink: true);

        $this->assertNull($second->fresh()->shared_link_burst_flagged_at, 'two in the window: no flag yet');

        $third = $this->reviewFor($doctor, '2026-10-05 09:00:00', viaLink: true);

        foreach ([$first, $second, $third] as $review) {
            $fresh = $review->fresh();
            $this->assertNotNull($fresh->shared_link_burst_flagged_at);
            $this->assertSame('pending', $fresh->status->value, 'flagged for extra review, never rejected');
        }
        $this->assertNull($early->fresh()->shared_link_burst_flagged_at, 'outside the 48 h window');
        $this->assertNull($plain->fresh()->shared_link_burst_flagged_at, 'not from the link');
        $this->assertNull($third->fresh()->burst_flagged_at, 'the general 5-in-24 h signal is separate');
        $this->assertSame(3, Review::query()->whereNotNull('shared_link_burst_flagged_at')->count());
        $this->assertSame(3, ReviewBurstDetector::SHARED_LINK_THRESHOLD);
    }

    public function test_staff_can_filter_the_queue_to_shared_link_bursts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $doctor = Doctor::factory()->create();
        $calm = $this->reviewFor($doctor, '2026-09-01 08:00:00', viaLink: true);
        $flagged = collect([1, 2, 3])->map(fn (int $hour) => $this->reviewFor($doctor, "2026-10-02 0{$hour}:00:00", viaLink: true));

        $moderator = User::factory()->create(['user_kind' => 'staff']);
        $moderator->syncRoles([RoleCatalog::MODERATOR]);
        $this->actingAs($moderator);

        Livewire::test(ListReviews::class)
            ->filterTable('shared_link_burst')
            ->assertCanSeeTableRecords($flagged)
            ->assertCanNotSeeTableRecords([$calm]);
    }

    private function member(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->forgetRateLimits();
        $member = User::factory()->create();
        $member->assignRole(RoleCatalog::MEMBER);

        return $member;
    }

    private function reviewFor(Doctor $doctor, string $at, bool $viaLink): Review
    {
        $this->travelTo($at);

        return Review::factory()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'via_shared_link' => $viaLink,
        ]);
    }
}
