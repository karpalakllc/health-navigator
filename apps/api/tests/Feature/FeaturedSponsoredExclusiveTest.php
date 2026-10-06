<?php

namespace Tests\Feature;

use App\Enums\UserKind;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Models\Doctor;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * „Истакнат“ is an unpaid editorial choice, „Спонзорирано“ a paid one; on one
 * card their explanations contradicted each other („без никакво плаќање“ next
 * to „плаќа“). A doctor is one or the other, never both.
 */
class FeaturedSponsoredExclusiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_form_refuses_both_flags(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);
        $doctor = Doctor::factory()->create(['is_sponsored' => true]);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm(['is_featured' => true, 'is_sponsored' => true])
            ->call('save')
            ->assertHasFormErrors(['is_featured']);

        $this->assertFalse($doctor->fresh()->is_featured);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm(['is_featured' => true, 'is_sponsored' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($doctor->fresh()->is_featured);
        $this->assertFalse($doctor->fresh()->is_sponsored);
    }

    public function test_the_model_refuses_both_flags_whatever_saves_it(): void
    {
        $doctor = Doctor::factory()->create(['is_featured' => true]);

        try {
            $doctor->update(['is_sponsored' => true]);
            $this->fail('A featured doctor was saved as sponsored.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_featured', $e->errors());
        }

        $this->assertFalse($doctor->fresh()->is_sponsored);
    }

    public function test_the_migration_keeps_sponsored_and_clears_featured(): void
    {
        $both = Doctor::factory()->create();
        $featured = Doctor::factory()->create(['is_featured' => true]);
        $sponsored = Doctor::factory()->create(['is_sponsored' => true]);
        DB::table('doctors')->where('id', $both->id)->update(['is_featured' => true, 'is_sponsored' => true]);

        $migration = require database_path('migrations/2026_10_13_140001_make_featured_and_sponsored_doctors_exclusive.php');
        $migration->up();

        $this->assertFalse($both->fresh()->is_featured);
        $this->assertTrue($both->fresh()->is_sponsored);
        $this->assertTrue($featured->fresh()->is_featured);
        $this->assertTrue($sponsored->fresh()->is_sponsored);
    }
}
