<?php

namespace Tests\Feature\Licences;

use App\Enums\UserKind;
use App\Filament\Resources\KomoraLicences\Pages\ListKomoraLicences;
use App\Filament\Resources\LicenceSpecialtyMappings\Pages\CreateLicenceSpecialtyMapping;
use App\Filament\Resources\LicenceSpecialtyMappings\Pages\EditLicenceSpecialtyMapping;
use App\Filament\Resources\LicenceSpecialtyMappings\Pages\ListLicenceSpecialtyMappings;
use App\Models\KomoraLicence;
use App\Models\LicenceSpecialtyMapping;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Licences\LicenceSpecialtyMap;
use App\Support\Licences\LicenceSpecialtySeedList;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The shipped specialty mapping, its admin editor and the read-only licence
 * staging list (licences.manage).
 */
class LicenceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        Filament::setCurrentPanel('admin');
    }

    public function test_the_shipped_mapping_is_complete_and_unambiguous(): void
    {
        $rows = LicenceSpecialtySeedList::rows();
        $keys = array_map(fn (array $row): string => $row['source'].'|'.$row['source_key'], $rows);

        $this->assertSame(count($keys), count(array_unique($keys)), 'Each wording is mapped once per source.');
        $this->assertSame(count($rows), LicenceSpecialtyMapping::query()->count());

        foreach ($rows as $row) {
            $this->assertTrue($row['group_key'] !== null || $row['is_ignored'], "{$row['source_text']} has a group or is ignored.");
        }

        $groups = array_unique(array_filter(array_column($rows, 'group_key')));

        foreach ($rows as $row) {
            foreach (json_decode($row['compatible_groups'] ?? '[]', true) as $compatible) {
                $this->assertContains($compatible, $groups, "{$row['source_text']}: „{$compatible}“ is a known group.");
            }
        }

        // The 118 wordings of the 02.07.2026 list.
        $this->assertSame(118, LicenceSpecialtyMapping::query()->where('source', 'komora')->count());
        $this->assertSame(0, LicenceSpecialtyMapping::query()->unmapped()->count());
    }

    public function test_licences_manage_belongs_to_the_administrator_role_only(): void
    {
        $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR, 'web')->hasPermissionTo('licences.manage'));
        $this->assertFalse(Role::findByName(RoleCatalog::MODERATOR, 'web')->hasPermissionTo('licences.manage'));
    }

    public function test_staff_map_new_wording_and_the_next_match_uses_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertNull((new LicenceSpecialtyMap)->licenceGroups('сосема нова специјалност'));

        $mapping = LicenceSpecialtyMapping::query()->create(['source' => 'komora', 'source_text' => 'сосема нова специјалност']);

        Livewire::test(ListLicenceSpecialtyMappings::class)
            ->filterTable('unmapped')
            ->assertCanSeeTableRecords([$mapping])
            ->assertCountTableRecords(1);

        Livewire::test(EditLicenceSpecialtyMapping::class, ['record' => $mapping->getRouteKey()])
            ->fillForm(['group_key' => ' Kardiologija ', 'compatible_groups' => ['interna-medicina']])
            ->call('save')
            ->assertHasNoFormErrors();

        $mapping->refresh();
        $this->assertSame('kardiologija', $mapping->group_key);
        $this->assertSame($admin->id, $mapping->reviewed_by_id);
        $this->assertNotNull($mapping->reviewed_at);
        $this->assertSame(['kardiologija', 'interna-medicina'], (new LicenceSpecialtyMap)->licenceGroups('СОСЕМА НОВА СПЕЦИЈАЛНОСТ'));
    }

    public function test_the_same_wording_cannot_be_added_twice(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateLicenceSpecialtyMapping::class)
            ->fillForm(['source' => 'komora', 'source_text' => 'КАРДИОЛОГИЈА', 'group_key' => 'kardiologija'])
            ->call('create')
            ->assertHasFormErrors(['source_text']);
    }

    public function test_the_licence_list_is_read_only_and_for_licence_managers_only(): void
    {
        $licence = KomoraLicence::query()->create([
            'licence_number' => '0000001',
            'full_name' => 'АНА ТЕСТОВСКА',
            'name_key' => 'АНА ТЕСТОВСКА',
            'specialty' => 'педијатрија',
            'valid_until' => '2020-01-01',
            'list_date' => '2026-07-02',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'outcome' => 'no_match',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListKomoraLicences::class)
            ->assertCanSeeTableRecords([$licence])
            ->filterTable('expired')
            ->assertCanSeeTableRecords([$licence]);

        $admin = auth()->user();
        $this->assertFalse($admin->can('update', $licence));
        $this->assertFalse($admin->can('delete', $licence));
        $this->assertFalse($admin->can('create', KomoraLicence::class));

        $moderator = User::factory()->create(['user_kind' => UserKind::Staff]);
        $moderator->syncRoles([RoleCatalog::MODERATOR]);
        $this->assertFalse($moderator->can('viewAny', KomoraLicence::class));
        $this->assertFalse($moderator->can('viewAny', LicenceSpecialtyMapping::class));
    }
}
