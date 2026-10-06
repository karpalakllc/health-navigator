<?php

namespace Tests\Feature\Filament;

use App\Enums\ReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Row and page approve/reject must require `reviews.update`, as the bulk actions
 * and the forum tables already do — a read-only reviews role must not moderate.
 */
class ReviewModerationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function staffWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
        ]);
        $user->syncRoles([$roleName]);

        return $user;
    }

    private function reviewViewer(): User
    {
        Role::findOrCreate('Review Viewer', 'web')->syncPermissions(['admin.access', 'reviews.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->staffWithRole('Review Viewer');
    }

    public function test_a_view_only_reviews_role_cannot_approve_or_reject_from_the_table(): void
    {
        $review = Review::factory()->create();
        $this->actingAs($this->reviewViewer());

        Livewire::test(ListReviews::class)
            ->assertCanSeeTableRecords([$review])
            ->assertTableActionHidden('approve', $review)
            ->assertTableActionHidden('reject', $review);

        $this->assertSame(ReviewStatus::Pending, $review->fresh()->status);
    }

    public function test_a_view_only_reviews_role_cannot_approve_or_reject_from_the_view_page(): void
    {
        $review = Review::factory()->create();
        $this->actingAs($this->reviewViewer());

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->assertActionHidden('approve')
            ->assertActionHidden('reject');
    }

    public function test_a_moderator_can_still_approve_from_the_table(): void
    {
        $review = Review::factory()->create();
        $this->actingAs($this->staffWithRole('Moderator'));

        Livewire::test(ListReviews::class)
            ->assertTableActionVisible('approve', $review)
            ->callTableAction('approve', $review);

        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);
    }
}
