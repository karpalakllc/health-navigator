<?php

namespace Tests\Feature\Filament;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use App\Support\Verification\Engine\EvidenceLoader;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The owner's side of verification in the import review queue: publish all
 * verified drafts at once after a glance at a sample, uncertain items first,
 * and one click to trust a flagged website.
 */
class VerificationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $user->syncRoles([$role]);

        return $user;
    }

    private function draft(bool $verified, string $name): Doctor
    {
        $doctor = Doctor::factory()->unpublished()->create(['full_name' => $name]);

        if ($verified) {
            app(VerificationWriter::class)->verify($doctor, VerificationBasis::OfficialRegisters, [['rule' => 'fzom_licence']]);
        }

        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$doctor->id, $name, [], $doctor);

        return $doctor;
    }

    public function test_publish_all_verified_publishes_only_verified_drafts_after_showing_a_sample(): void
    {
        $first = $this->draft(true, 'Ана Проверена');
        $second = $this->draft(true, 'Бранко Проверен');
        $unverified = $this->draft(false, 'Вера Непроверена');
        $facility = Facility::factory()->unpublished()->create();
        app(VerificationWriter::class)->verify($facility, VerificationBasis::OfficialRegisters);
        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'facility:'.$facility->id, 'Установа', [], $facility);

        // Viewing the queue is not enough to publish.
        $viewer = $this->staff(RoleCatalog::MODERATOR);
        $viewer->givePermissionTo(Permission::findByName('imports.view'));
        $this->actingAs($viewer);
        Livewire::test(ListImportReviewItems::class)->assertActionHidden('publishVerified');

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        Livewire::test(ListImportReviewItems::class)
            ->assertActionVisible('publishVerified')
            ->mountAction('publishVerified')
            ->assertMountedActionModalSee('Publish 3 verified drafts?')
            ->assertMountedActionModalSee('Ана Проверена')
            ->assertMountedActionModalDontSee('Вера Непроверена')
            ->callMountedAction();

        $this->assertTrue($first->fresh()->is_published);
        $this->assertTrue($second->fresh()->is_published);
        $this->assertTrue($facility->fresh()->is_published);
        $this->assertFalse($unverified->fresh()->is_published);
        $this->assertSame(1, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::New)->count());

        Livewire::test(ListImportReviewItems::class)->assertActionHidden('publishVerified');
    }

    public function test_uncertain_items_come_first_and_a_flagged_website_can_be_trusted(): void
    {
        $facility = Facility::factory()->create();
        $ordinary = ImportReviewItem::raise('website', ImportReviewKind::Unmatched, 'specialty:website:x', 'Unmapped', ['raw' => 'x']);
        $flagged = ImportReviewItem::raise(EvidenceLoader::ENGINE_SOURCE, ImportReviewKind::Uncertain, EvidenceLoader::FLAGGED_SITE_KEY.$facility->id,
            'Website flagged as compromised', ['reason' => 'flagged_source', 'doctors' => 3], $facility, null, 80);
        $pair = ImportReviewItem::raise(EvidenceLoader::ENGINE_SOURCE, ImportReviewKind::Uncertain, 'specialty-pair:x', 'Pair', ['reason' => 'specialty_mapping'], null, null, 20);

        $this->actingAs($this->staff(RoleCatalog::ADMINISTRATOR));
        Livewire::test(ListImportReviewItems::class)
            ->assertCanSeeTableRecords([$flagged, $pair, $ordinary], inOrder: true)
            ->assertTableActionHidden('trustSite', $pair)
            ->callTableAction('trustSite', $flagged);

        $this->assertSame(ImportReviewStatus::Resolved, $flagged->fresh()->status);
        $this->assertSame(EvidenceLoader::TRUSTED_RESOLUTION, $flagged->fresh()->resolution);
    }
}
