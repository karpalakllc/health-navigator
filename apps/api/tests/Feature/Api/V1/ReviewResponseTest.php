<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserKind;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Right of reply: staff attach the reviewed profile's official response, the
 * public review list shows it, signed with the profile's name.
 */
class ReviewResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function doctorReview(): Review
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'full_name' => 'д-р Ана Петровска']);

        return Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);
    }

    public function test_reviews_without_a_response_carry_null(): void
    {
        $this->doctorReview();

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.response', null);
    }

    public function test_the_response_renders_under_the_review_signed_with_the_profile_name(): void
    {
        $review = $this->doctorReview();
        $review->respond($this->staff(RoleCatalog::MODERATOR), "Ви благодариме.\n\nЌе го подобриме закажувањето.");

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.response.body', "Ви благодариме.\n\nЌе го подобриме закажувањето.")
            ->assertJsonPath('data.0.response.responder_name', 'д-р Ана Петровска')
            ->assertJsonPath('data.0.response.responded_at', $review->fresh()->response_at->toIso8601String());
    }

    public function test_facility_responses_are_signed_with_the_facility_name(): void
    {
        $facility = Facility::factory()->create(['slug' => 'klinika', 'name' => 'Клиника Здравје']);
        $review = Review::factory()->approved()->create([
            'reviewable_type' => Facility::class,
            'reviewable_id' => $facility->id,
        ]);
        $review->respond($this->staff(RoleCatalog::MODERATOR), 'Благодариме.');

        $this->getJson('/api/v1/facilities/klinika/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.response.responder_name', 'Клиника Здравје');
    }

    public function test_responses_are_stored_as_plain_text(): void
    {
        $review = $this->doctorReview();

        $review->respond($this->staff(RoleCatalog::MODERATOR), "  <script>alert(1)</script><b>Благодариме</b>   \r\n\r\n\r\n\r\nПоздрав  ");

        $this->assertSame("alert(1)Благодариме\n\nПоздрав", $review->fresh()->response_body);
    }

    public function test_a_moderator_adds_edits_and_removes_a_response_in_the_panel(): void
    {
        $review = $this->doctorReview();
        $moderator = $this->staff(RoleCatalog::MODERATOR);
        $this->actingAs($moderator);

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->callAction('respond', ['response_body' => 'Прв одговор.']);

        $review->refresh();
        $this->assertSame('Прв одговор.', $review->response_body);
        $this->assertSame($moderator->id, $review->response_by_id);
        $this->assertNotNull($review->response_at);

        Livewire::test(ListReviews::class)
            ->filterTable('status', 'approved')
            ->callTableAction('respond', $review, ['response_body' => 'Изменет одговор.']);
        $this->assertSame('Изменет одговор.', $review->fresh()->response_body);

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->callAction('removeResponse');

        $review->refresh();
        $this->assertNull($review->response_body);
        $this->assertNull($review->response_at);
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertJsonPath('data.0.response', null);
    }

    public function test_the_response_length_is_limited(): void
    {
        $review = $this->doctorReview();
        $this->actingAs($this->staff(RoleCatalog::MODERATOR));

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->callAction('respond', ['response_body' => str_repeat('а', Review::RESPONSE_MAX_LENGTH + 1)])
            ->assertHasActionErrors(['response_body' => 'max']);

        $this->assertNull($review->fresh()->response_body);
    }

    public function test_a_role_without_reviews_respond_cannot_respond(): void
    {
        Role::findOrCreate('Review Viewer', 'web')->syncPermissions(['admin.access', 'reviews.view', 'reviews.update']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $review = $this->doctorReview();
        $review->respond($this->staff(RoleCatalog::MODERATOR), 'Постоечки одговор.');
        $this->actingAs($this->staff('Review Viewer'));

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->assertActionHidden('respond')
            ->assertActionHidden('removeResponse');
    }

    public function test_pending_reviews_cannot_get_a_response(): void
    {
        $review = Review::factory()->create();
        $this->actingAs($this->staff(RoleCatalog::MODERATOR));

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->assertActionHidden('respond');
    }
}
