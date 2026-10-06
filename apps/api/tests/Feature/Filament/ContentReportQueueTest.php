<?php

namespace Tests\Feature\Filament;

use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\ContentReports\ContentReportResource;
use App\Filament\Resources\ContentReports\Pages\ListContentReports;
use App\Filament\Resources\ContentReports\Pages\ViewContentReport;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContentReportQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        Mail::fake();
    }

    private function staffWithRole(string $roleName): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->syncRoles([$roleName]);

        return $user;
    }

    private function reportedReview(): ContentReport
    {
        $review = Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::factory(),
        ]);

        return ContentReport::factory()->about($review)->create();
    }

    public function test_built_in_staff_roles_hold_the_queue_permissions_and_others_do_not(): void
    {
        foreach ([RoleCatalog::ADMINISTRATOR, RoleCatalog::MODERATOR] as $role) {
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('content_reports.view'), $role);
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('content_reports.resolve'), $role);
            $this->assertTrue(Role::findByName($role)->hasPermissionTo('reviews.respond'), $role);
        }

        foreach ([RoleCatalog::FORUM_MODERATOR, RoleCatalog::MEMBER] as $role) {
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('content_reports.view'), $role);
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('reviews.respond'), $role);
        }
    }

    public function test_the_permission_migration_grants_existing_roles_additively(): void
    {
        $moderator = Role::findByName(RoleCatalog::MODERATOR);
        Permission::query()->whereIn('name', PermissionCatalog::reportsAndResponses())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $moderator->revokePermissionTo('forum_topics.update'); // an admin's own trim

        $migration = require database_path('migrations/2026_10_13_120002_grant_report_and_response_permissions.php');
        $migration->up();
        $migration->up(); // re-runnable

        $moderator = Role::findByName(RoleCatalog::MODERATOR)->fresh();
        foreach (PermissionCatalog::reportsAndResponses() as $permission) {
            $this->assertTrue($moderator->hasPermissionTo($permission), $permission);
        }
        $this->assertFalse($moderator->hasPermissionTo('forum_topics.update'));
        $this->assertFalse(Role::findByName(RoleCatalog::FORUM_MODERATOR)->hasPermissionTo('content_reports.view'));
    }

    public function test_a_moderator_sees_open_reports_and_hides_the_content(): void
    {
        $report = $this->reportedReview();
        $resolved = ContentReport::factory()->create(['status' => ReportStatus::Kept]);
        $moderator = $this->staffWithRole(RoleCatalog::MODERATOR);
        $this->actingAs($moderator);

        Livewire::test(ListContentReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertCanNotSeeTableRecords([$resolved])
            ->callTableAction('hide', $report, ['note' => 'Навредлива содржина.']);

        $this->assertSame(ReportStatus::Hidden, $report->fresh()->status);
        $this->assertSame($moderator->id, $report->fresh()->resolved_by_id);
        $review = Review::query()->findOrFail($report->reportable_id);
        $this->assertSame(ReviewStatus::Rejected, $review->status);
        $this->assertSame('Навредлива содржина.', $review->rejection_note);
        $this->assertSame($moderator->id, $review->moderated_by_id);
    }

    public function test_a_moderator_keeps_content_from_the_view_page(): void
    {
        $report = $this->reportedReview();
        $this->actingAs($this->staffWithRole(RoleCatalog::MODERATOR));

        Livewire::test(ViewContentReport::class, ['record' => $report->getRouteKey()])
            ->callAction('keep');

        $this->assertSame(ReportStatus::Kept, $report->fresh()->status);
        $this->assertSame(ReviewStatus::Approved, Review::query()->findOrFail($report->reportable_id)->status);
    }

    public function test_a_view_only_reports_role_cannot_resolve(): void
    {
        Role::findOrCreate('Report Viewer', 'web')->syncPermissions(['admin.access', 'content_reports.view', 'reviews.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $report = $this->reportedReview();
        $this->actingAs($this->staffWithRole('Report Viewer'));

        Livewire::test(ListContentReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertTableActionHidden('hide', $report)
            ->assertTableActionHidden('keep', $report);

        Livewire::test(ViewContentReport::class, ['record' => $report->getRouteKey()])
            ->assertActionHidden('hide')
            ->assertActionHidden('keep');

        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
    }

    public function test_resolving_without_rights_over_the_content_cannot_hide_it(): void
    {
        Role::findOrCreate('Report Triage', 'web')->syncPermissions(['admin.access', 'content_reports.view', 'content_reports.resolve']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $report = $this->reportedReview();
        $this->actingAs($this->staffWithRole('Report Triage'));

        Livewire::test(ListContentReports::class)
            ->assertTableActionHidden('hide', $report)
            ->assertTableActionVisible('keep', $report);
    }

    public function test_the_queue_is_closed_to_forum_moderators_and_members(): void
    {
        $forumModerator = User::factory()->create();
        $forumModerator->assignRole(RoleCatalog::FORUM_MODERATOR);

        $this->actingAs($forumModerator)
            ->get(ContentReportResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(ContentReportResource::getUrl('index'))
            ->assertForbidden();
    }
}
