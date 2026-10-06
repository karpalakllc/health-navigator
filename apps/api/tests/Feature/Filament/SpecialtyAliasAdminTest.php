<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Resources\SpecialtyAliases\Pages\EditSpecialtyAlias;
use App\Filament\Resources\SpecialtyAliases\Pages\ListSpecialtyAliases;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use App\Models\User;
use App\Support\Import\Fzom\FzomImportJob;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The specialty alias editor (Data import → Specialty aliases): staff map a
 * source's wording to our specialty, and the next import uses it.
 */
class SpecialtyAliasAdminTest extends TestCase
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

    private function import(): void
    {
        app(FzomImportJob::class)->run(false, null, [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);
    }

    public function test_a_moderator_cannot_open_the_alias_editor(): void
    {
        $this->actingAs($this->staff(RoleCatalog::MODERATOR))
            ->get('/admin/specialty-aliases')
            ->assertForbidden();
    }

    public function test_mapping_an_unmapped_wording_relinks_its_doctors_on_the_next_run(): void
    {
        $this->import();
        $alias = SpecialtyAlias::query()->where('raw_key', 'НЕПОЗНАТА НОВА СПЕЦИЈАЛНОСТ')->firstOrFail();
        $this->assertNull($alias->specialty_id);
        $cardiology = Specialty::query()->where('slug', 'kardiologija')->firstOrFail();
        $linked = DB::table('doctor_specialty')->where('specialty_id', $cardiology->id)->count();

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        Livewire::test(ListSpecialtyAliases::class)
            ->filterTable('unmapped')
            ->assertCanSeeTableRecords([$alias]);

        Livewire::test(EditSpecialtyAlias::class, ['record' => $alias->getRouteKey()])
            ->fillForm(['specialty_id' => $cardiology->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($cardiology->id, $alias->refresh()->specialty_id);

        $this->import();

        $this->assertSame($linked + 1, DB::table('doctor_specialty')->where('specialty_id', $cardiology->id)->count());
    }

    public function test_an_excluded_wording_loses_its_specialty(): void
    {
        $this->import();
        $alias = SpecialtyAlias::query()->whereNotNull('specialty_id')->where('is_excluded', false)->firstOrFail();

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        Livewire::test(EditSpecialtyAlias::class, ['record' => $alias->getRouteKey()])
            ->fillForm(['is_excluded' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $alias->refresh();
        $this->assertTrue($alias->is_excluded);
        $this->assertNull($alias->specialty_id);
        // Source and wording are read-only.
        $this->assertSame('fzom', $alias->source);
    }
}
