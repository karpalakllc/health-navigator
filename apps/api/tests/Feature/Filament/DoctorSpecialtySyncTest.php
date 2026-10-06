<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Resources\Doctors\Pages\CreateDoctor;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Http\Controllers\Api\V1\HomeHighlightsController;
use App\Http\Controllers\Api\V1\SpecialtyController;
use App\Models\Doctor;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeMeilisearchEngine;
use Tests\TestCase;

/**
 * Filament saves the doctor, then syncs its specialties. The save flushes the
 * cached specialty list, but the pivot sync fires no model event — so a list
 * read in between (another visitor's request) re-cached the pre-sync
 * doctors_count for the cache's lifetime, and the doctor's search document
 * kept its old specialties.
 */
class DoctorSpecialtySyncTest extends TestCase
{
    use RefreshDatabase;

    private Specialty $cardiology;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();

        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);

        $this->cardiology = Specialty::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);
    }

    /** Another request reading the list after the doctor row is saved but before the pivot sync. */
    private function readTheListBetweenSaveAndSync(): void
    {
        // The controller directly: a nested HTTP request would reset the
        // Livewire test's state.
        Doctor::saved(fn () => app(SpecialtyController::class)->index());
    }

    private function listedCount(): int
    {
        return $this->getJson('/api/v1/specialties')
            ->assertOk()
            ->json('data.0.doctors_count');
    }

    public function test_creating_a_doctor_refreshes_the_specialty_counts(): void
    {
        $this->assertSame(0, $this->listedCount());
        $this->readTheListBetweenSaveAndSync();

        Livewire::test(CreateDoctor::class)
            ->fillForm([
                'full_name' => 'Ана Петровска',
                'slug' => 'ana-petrovska',
                'is_published' => true,
                'specialty_ids' => [$this->cardiology->id],
                'primary_specialty_id' => $this->cardiology->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $this->listedCount());
    }

    public function test_editing_a_doctors_specialties_refreshes_the_home_highlights(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $this->getJson('/api/v1/home/highlights')->assertOk()->assertJsonCount(0, 'data.specialties');
        // Another request re-caches the highlights after the doctor row is saved but before the pivot sync.
        Doctor::saved(fn () => app(HomeHighlightsController::class)());

        Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm([
                'specialty_ids' => [$this->cardiology->id],
                'primary_specialty_id' => $this->cardiology->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/v1/home/highlights')->assertOk()
            ->assertJsonPath('data.specialties.0.slug', 'kardiologija');
    }

    public function test_editing_a_doctors_specialties_refreshes_the_counts_and_the_search_document(): void
    {
        $engine = FakeMeilisearchEngine::install();
        $doctor = Doctor::factory()->create(['is_published' => true]);

        $this->assertSame(0, $this->listedCount());
        $this->readTheListBetweenSaveAndSync();

        Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm([
                'specialty_ids' => [$this->cardiology->id],
                'primary_specialty_id' => $this->cardiology->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $this->listedCount());
        $this->assertSame(['Кардиологија'], $engine->indexes['doctors'][$doctor->id]['specialty_names']);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getKey()])
            ->fillForm(['specialty_ids' => [], 'primary_specialty_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $this->listedCount());
        $this->assertSame([], $engine->indexes['doctors'][$doctor->id]['specialty_names']);
    }
}
