<?php

namespace Tests\Unit;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DirectoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_moderator_can_view_but_not_mutate_directory_records(): void
    {
        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');
        $doctor = Doctor::factory()->create();
        $facility = Facility::factory()->create();

        $this->assertTrue($moderator->can('viewAny', Doctor::class));
        $this->assertTrue($moderator->can('view', $doctor));
        $this->assertFalse($moderator->can('create', Doctor::class));
        $this->assertFalse($moderator->can('update', $doctor));
        $this->assertFalse($moderator->can('delete', $doctor));

        $this->assertTrue($moderator->can('view', $facility));
        $this->assertFalse($moderator->can('update', $facility));
    }

    public function test_admin_can_mutate_directory_records(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole('Administrator');
        $doctor = Doctor::factory()->create();

        $this->assertTrue($admin->can('update', $doctor));
        $this->assertTrue($admin->can('create', Doctor::class));
    }

    public function test_moderator_can_still_moderate_reviews(): void
    {
        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');

        $this->assertTrue($moderator->can('viewAny', Review::class));
    }

    public function test_pharmacy_facility_uses_same_directory_policy_as_clinical(): void
    {
        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');
        $admin = User::factory()->admin()->create();
        $admin->assignRole('Administrator');
        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy]);

        $this->assertTrue($moderator->can('view', $pharmacy));
        $this->assertFalse($moderator->can('update', $pharmacy));
        $this->assertTrue($admin->can('update', $pharmacy));
    }
}
