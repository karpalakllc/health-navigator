<?php

namespace Tests\Feature\Filament;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\ImportReviewItems\ImportReviewItemResource;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Filament\Resources\ImportReviewItems\Pages\ViewImportReviewItem;
use App\Filament\Resources\ImportRuns\Pages\ListImportRuns;
use App\Filament\Resources\ImportSuppressions\Pages\ListImportSuppressions;
use App\Models\Doctor;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\ImportSuppression;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Import\Fzom\FzomImportJob;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staff side of the imports: runs, the review queue, bulk publishing,
 * conflicts and field locks, and who may use them.
 */
class ImportAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        Storage::fake('local');
        config(['import.disk' => 'local']);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function importFixtures(): ImportRun
    {
        return app(FzomImportJob::class)->run(false, null, [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);
    }

    public function test_only_the_administrator_holds_the_import_permissions_and_the_migration_is_additive(): void
    {
        foreach (PermissionCatalog::imports() as $permission) {
            $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR)->hasPermissionTo($permission), $permission);
            $this->assertFalse(Role::findByName(RoleCatalog::MODERATOR)->hasPermissionTo($permission), $permission);
        }

        Permission::query()->whereIn('name', PermissionCatalog::imports())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $migration = require database_path('migrations/2026_10_15_100002_grant_import_permissions.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR)->fresh()->hasPermissionTo('imports.manage'));
    }

    public function test_a_moderator_cannot_open_the_import_pages(): void
    {
        $this->importFixtures();
        $this->actingAs($this->staff(RoleCatalog::MODERATOR));

        $this->get('/admin/import-runs')->assertForbidden();
        $this->get('/admin/import-review-items')->assertForbidden();
    }

    public function test_an_administrator_bulk_publishes_drafts_and_their_imported_specialties(): void
    {
        $this->importFixtures();
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $this->actingAs($admin);

        $this->get('/admin/import-runs')->assertOk();
        $this->get('/admin/import-review-items')->assertOk();
        Livewire::test(ListImportRuns::class)->assertCanSeeTableRecords(ImportRun::all());

        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $item = ImportReviewItem::query()->where('kind', ImportReviewKind::New)->where('subject_type', 'doctor')->where('subject_id', $doctor->getKey())->firstOrFail();

        Livewire::test(ListImportReviewItems::class)
            ->callTableBulkAction('publish', [$item]);

        $doctor->refresh();
        $this->assertTrue($doctor->is_published);
        $this->assertNotNull($doctor->published_at);
        $this->assertTrue((bool) Specialty::query()->where('slug', 'kardiologija')->value('is_published'));
        $this->assertSame(ImportReviewStatus::Resolved, $item->refresh()->status);
        $this->assertSame($admin->getKey(), $item->resolved_by_id);
        // Other drafts stay hidden.
        $this->assertFalse(Doctor::query()->where('fzo_facsimile', '900001')->firstOrFail()->is_published);
        $this->getJson('/api/v1/doctors/'.$doctor->slug)->assertOk();
    }

    public function test_conflicts_can_be_accepted_or_kept_and_locked(): void
    {
        $this->importFixtures();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $doctor->update(['city' => 'Рачно', 'full_name' => 'Рачно Име']);
        $spec = str_replace(['<Mesto>ТЕСТОВО</Mesto>', 'ЧЕТВРТИ'], ['<Mesto>НОВО</Mesto>', 'ЧЕТВРТИНА'], (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $path = tempnam(sys_get_temp_dir(), 'fzom');
        file_put_contents((string) $path, $spec);
        app(FzomImportJob::class)->run(false, null, ['pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'), 'spec' => (string) $path]);

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        $cityConflict = ImportReviewItem::query()->where('item_key', 'doctor:'.$doctor->getKey().':city')->firstOrFail();
        $nameConflict = ImportReviewItem::query()->where('item_key', 'doctor:'.$doctor->getKey().':full_name')->firstOrFail();

        Livewire::test(ViewImportReviewItem::class, ['record' => $cityConflict->getRouteKey()])->callAction('accept');
        Livewire::test(ViewImportReviewItem::class, ['record' => $nameConflict->getRouteKey()])->callAction('keep');

        $doctor->refresh();
        $this->assertSame('Ново', $doctor->city);
        $this->assertSame('Рачно Име', $doctor->full_name);
        $this->assertTrue((bool) FieldProvenance::query()->where('subject_id', $doctor->getKey())->where('subject_type', 'doctor')->where('field', 'full_name')->value('locked'));
    }

    public function test_the_lock_action_on_the_doctor_page_saves_locks(): void
    {
        $this->importFixtures();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->callAction('importLocks', ['locked' => ['city', 'specialties']])
            ->assertHasNoActionErrors();

        $locked = FieldProvenance::query()->where('subject_type', 'doctor')->where('subject_id', $doctor->getKey())->where('locked', true)->pluck('field')->sort()->values()->all();
        $this->assertSame(['city', 'specialties'], $locked);
    }

    public function test_the_diff_summary_downloads_for_staff_only(): void
    {
        $run = $this->importFixtures();
        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));

        Livewire::test(ListImportRuns::class)
            ->callTableAction('downloadDiff', $run)
            ->assertFileDownloaded('import-fzom-'.$run->getKey().'.csv');
    }

    public function test_suppressed_profiles_are_listed_and_only_imports_manage_lifts_one(): void
    {
        $doctor = Doctor::factory()->create(['full_name' => 'Измислен Отстранет']);
        $doctor->delete();
        $suppression = ImportSuppression::query()->active()->firstOrFail();

        $this->actingAs($this->staff(RoleCatalog::MODERATOR));
        $this->get('/admin/import-suppressions')->assertForbidden();

        $viewer = $this->staff(RoleCatalog::ADMINISTRATOR);
        $viewer->syncRoles([]);
        $viewer->givePermissionTo(['admin.access', 'imports.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($viewer);
        Livewire::test(ListImportSuppressions::class)
            ->assertCanSeeTableRecords([$suppression])
            ->assertSee('Измислен Отстранет')
            ->assertTableActionHidden('lift', $suppression);

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        Livewire::test(ListImportSuppressions::class)->callTableAction('lift', $suppression);

        $this->assertFalse($suppression->refresh()->isActive());
    }

    public function test_the_profile_link_of_a_review_item_is_shown_only_to_staff_who_may_edit_the_profile(): void
    {
        $this->importFixtures();
        $item = ImportReviewItem::query()->where('kind', ImportReviewKind::New)->where('subject_type', 'doctor')->firstOrFail();

        $viewer = $this->staff(RoleCatalog::ADMINISTRATOR);
        $viewer->syncRoles([]);
        $viewer->givePermissionTo(['admin.access', 'imports.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($viewer);
        $this->assertNull(ImportReviewItemResource::subjectUrl($item));

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        $this->assertNotNull(ImportReviewItemResource::subjectUrl($item));
    }
}
