<?php

namespace Tests\Feature\Filament;

use App\Enums\FacilityType;
use App\Enums\UserKind;
use App\Filament\Resources\ClinicalInterests\Pages\ListClinicalInterests;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Filament\Resources\ForumCategories\Pages\ListForumCategories;
use App\Filament\Resources\Languages\Pages\ListLanguages;
use App\Filament\Resources\Pharmacies\Pages\ListPharmacies;
use App\Filament\Resources\Pharmacies\PharmacyResource;
use App\Filament\Resources\Procedures\Pages\ListProcedures;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Specialties\Pages\ListSpecialties;
use App\Models\ClinicalInterest;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\Language;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use Closure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Filament (non-strict mode) *allows* any ability whose method is missing from a
 * registered policy. The directory policies used to define delete/forceDelete/
 * restore but not their `*Any` bulk counterparts, so the view-only Moderator role
 * could bulk delete every directory and taxonomy record.
 */
class ResourceAuthorizationCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every ability Filament's resources, tables and relation managers consult.
     */
    private const FILAMENT_ABILITIES = [
        'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny',
        'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny',
        'reorder', 'replicate',
        'attach', 'detach', 'detachAny', 'associate', 'dissociate', 'dissociateAny',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function viewOnlyModerator(): User
    {
        $moderator = User::factory()->create([
            'user_kind' => UserKind::Staff,
        ]);
        $moderator->syncRoles(['Moderator']);

        return $moderator;
    }

    /**
     * @return array<string, array{class-string, Closure(): Model}>
     */
    public static function bulkDeletableResources(): array
    {
        return [
            'doctors' => [ListDoctors::class, fn () => Doctor::factory()->create()],
            'facilities' => [ListFacilities::class, fn () => Facility::factory()->create(['type' => FacilityType::Hospital])],
            'pharmacies' => [ListPharmacies::class, fn () => Facility::factory()->create(['type' => FacilityType::Pharmacy])],
            'products' => [ListProducts::class, fn () => Product::factory()->create()],
            'specialties' => [ListSpecialties::class, fn () => Specialty::factory()->create()],
            'departments' => [ListDepartments::class, fn () => Department::factory()->create()],
            'procedures' => [ListProcedures::class, fn () => Procedure::factory()->create()],
            'clinical interests' => [ListClinicalInterests::class, fn () => ClinicalInterest::factory()->create()],
            'languages' => [ListLanguages::class, fn () => Language::factory()->create()],
            'forum categories' => [ListForumCategories::class, fn () => ForumCategory::factory()->create()],
        ];
    }

    /**
     * @param  class-string  $listPage
     * @param  Closure(): Model  $makeRecord
     */
    #[DataProvider('bulkDeletableResources')]
    public function test_a_view_only_role_cannot_bulk_delete_force_delete_or_restore(string $listPage, Closure $makeRecord): void
    {
        $this->actingAs($this->viewOnlyModerator());

        $record = $makeRecord();
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($record), true);

        $page = Livewire::test($listPage)->assertTableBulkActionHidden('delete');

        if ($softDeletes) {
            $page->assertTableBulkActionHidden('forceDelete')
                ->assertTableBulkActionHidden('restore');
        }

        $this->assertDatabaseHas($record->getTable(), [$record->getKeyName() => $record->getKey()]);
    }

    public function test_pharmacy_actions_check_pharmacy_permissions_not_facility_permissions(): void
    {
        // facilities.delete alone must not delete a pharmacy: the Gate resolves
        // FacilityPolicy for the shared model, which is the wrong permission set.
        $user = $this->viewOnlyModerator();
        $user->givePermissionTo(['pharmacies.view', 'facilities.delete']);
        $this->actingAs($user);

        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy]);

        Livewire::test(ListPharmacies::class)->assertTableBulkActionHidden('delete');

        $user->givePermissionTo('pharmacies.delete');
        $user->revokePermissionTo('facilities.delete');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(ListPharmacies::class)->callTableBulkAction('delete', [$pharmacy]);
        $this->assertSoftDeleted($pharmacy);
    }

    /**
     * Structural guard so a new resource or policy cannot reintroduce the gap.
     */
    public function test_every_resource_policy_defines_every_ability_filament_checks(): void
    {
        $resources = Filament::getPanel('admin')->getResources();
        $this->assertNotEmpty($resources);

        foreach ($resources as $resource) {
            if ($resource === PharmacyResource::class) {
                // Routes every ability through PharmacyPolicy itself, denying unknown ones.
                continue;
            }

            $policy = Gate::getPolicyFor($resource::getModel());
            $this->assertNotNull($policy, "{$resource} has no policy; Filament would allow everything.");

            foreach (self::FILAMENT_ABILITIES as $ability) {
                $this->assertTrue(
                    method_exists($policy, $ability),
                    $policy::class."::{$ability}() is missing (used by {$resource}); Filament treats a missing method as allow.",
                );
            }
        }
    }
}
