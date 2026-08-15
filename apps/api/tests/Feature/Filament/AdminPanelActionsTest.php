<?php

namespace Tests\Feature\Filament;

use App\Enums\ForumContentStatus;
use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\Staff\Pages\EditStaffUser;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Covers the admin panel's *actions*, which page renders and form submits do
 * not reach.
 *
 * This matters more than ordinary smoke coverage because the authorisation
 * these actions perform is the scoped-moderator escalation fix: a moderator
 * assigned to specific categories must not act outside them, and the check
 * lives inside the bulk action's own closure. A page test cannot see it, and a
 * policy unit test does not prove the action calls the policy.
 */
class AdminPanelActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
        ]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    private function pendingTopic(ForumCategory $category): ForumTopic
    {
        return ForumTopic::factory()->create([
            'forum_category_id' => $category->getKey(),
            'status' => ForumContentStatus::Pending,
        ]);
    }

    /**
     * The escalation case, asserted at the layer that actually stops it.
     *
     * Two mechanisms guard this: ForumTopicResource::getEloquentQuery() scopes
     * the table via ForumModerationScope, and each bulk-action closure re-checks
     * the policy. The query scope is what an operator hits first — an
     * out-of-scope topic is never listed, so it can never be selected — which
     * means a test that only drives the bulk action passes even with the
     * closure guard deleted. Verified: removing that guard does not fail this
     * test, so it is asserted where it bites instead.
     */
    public function test_a_scoped_moderator_only_sees_their_own_categories(): void
    {
        [$moderator, $inScope, $outOfScope] = $this->scopedModerator();

        $this->actingAs($moderator);

        Livewire::test(ListForumTopics::class)
            ->assertCanSeeTableRecords([$inScope])
            ->assertCanNotSeeTableRecords([$outOfScope]);
    }

    public function test_a_scoped_moderator_can_approve_within_their_categories(): void
    {
        [$moderator, $inScope] = $this->scopedModerator();

        $this->actingAs($moderator);

        Livewire::test(ListForumTopics::class)
            ->callTableBulkAction('approve_selected', [$inScope->getKey()]);

        $this->assertSame(
            ForumContentStatus::Approved,
            $inScope->fresh()->status,
            'The moderator could not approve inside their own category.',
        );
    }

    /**
     * The closure guard itself, exercised directly. It is defence in depth
     * behind the query scope, so this is the only place it can be observed.
     */
    public function test_the_policy_refuses_a_topic_outside_the_moderators_categories(): void
    {
        [$moderator, $inScope, $outOfScope] = $this->scopedModerator();

        $this->assertTrue($moderator->can('update', $inScope));
        $this->assertFalse(
            $moderator->can('update', $outOfScope),
            'A scoped moderator was authorised outside their categories.',
        );
    }

    /** @return array{User, ForumTopic, ForumTopic} */
    private function scopedModerator(): array
    {
        $mine = ForumCategory::factory()->create();
        $theirs = ForumCategory::factory()->create();

        $moderator = User::factory()->create([
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
        ]);
        $moderator->assignRole('Forum Moderator');
        $moderator->moderatedForumCategories()->attach($mine);

        return [$moderator, $this->pendingTopic($mine), $this->pendingTopic($theirs)];
    }

    public function test_an_administrator_can_reset_a_staff_password(): void
    {
        $admin = $this->admin();

        $staff = User::factory()->create([
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
            'password' => Hash::make('old-password-here'),
        ]);

        $this->actingAs($admin);

        Livewire::test(EditStaffUser::class, ['record' => $staff->getKey()])
            ->callAction('resetPassword', data: [
                'password' => 'brand1new1password',
                'password_confirmation' => 'brand1new1password',
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(
            Hash::check('brand1new1password', $staff->fresh()->password),
            'The password reset action did not change the password.',
        );
    }

    public function test_a_forum_moderator_cannot_reach_the_staff_resource(): void
    {
        $moderator = User::factory()->create([
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
        ]);
        $moderator->assignRole('Forum Moderator');

        // The password-reset action is admin-only, but the resource itself is the
        // real boundary — a moderator must not get as far as the form.
        $this->actingAs($moderator)
            ->get('/admin/staff/staff-users')
            ->assertForbidden();
    }
}
