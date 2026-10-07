<?php

namespace Tests\Feature\Filament;

use App\Enums\FacilityType;
use App\Enums\UserKind;
use App\Filament\Pages\FeedbackReport;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Models\Facility;
use App\Models\User;
use App\Support\Feedback\FeedbackRecorder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staff side of „Каде веднаш“ and „Дали ви помогна?“ (docs/urgent-care.md):
 * the urgent-care fields on the facility form, the „to check“ filter, and the
 * feedback report.
 */
class UrgentCareAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    public function test_staff_edit_urgent_care_fields_and_see_the_import_evidence(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Clinic]);
        $facility->forceFill(['urgent_care_evidence' => [
            'items' => [['flag' => 'ems', 'strength' => 'strong', 'source' => 'fzom', 'kind' => 'work_unit', 'text' => 'Служба за итна медицинска помош']],
            'derived' => ['ems'],
        ]])->save();

        $this->actingAs($this->admin());

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->assertSee('Служба за итна медицинска помош')
            ->fillForm([
                'has_emergency_medical_service' => true,
                'has_dental_emergency' => true,
                'emergency_hours' => ['row-1' => ['day' => 'Пон', 'hours' => '07:00–20:00'], 'row-2' => ['day' => 'Саб', 'hours' => '08:00–14:00']],
                'emergency_phone' => '047 000 000',
                'urgent_care_note' => 'Влез од задната страна',
                'urgent_care_checked_at' => '2026-10-07 10:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $facility->refresh();
        $this->assertTrue($facility->has_emergency_medical_service);
        $this->assertTrue($facility->has_dental_emergency);
        $this->assertSame(['Пон' => '07:00–20:00', 'Саб' => '08:00–14:00'], $facility->emergency_hours);
        $this->assertSame('047 000 000', $facility->emergency_phone);
        $this->assertSame('2026-10-07', $facility->urgent_care_checked_at?->toDateString());
    }

    public function test_the_to_check_filter_lists_unconfirmed_import_evidence(): void
    {
        $toCheck = Facility::factory()->create(['urgent_care_evidence' => ['items' => [['flag' => 'ed']]]]);
        $confirmed = Facility::factory()->create(['urgent_care_evidence' => ['items' => [['flag' => 'ed']]], 'urgent_care_checked_at' => now()]);
        $none = Facility::factory()->create();
        $ems = Facility::factory()->create(['has_emergency_medical_service' => true]);

        $this->actingAs($this->admin());

        Livewire::test(ListFacilities::class)
            ->filterTable('urgent_care', 'to_check')
            ->assertCanSeeTableRecords([$toCheck])
            ->assertCanNotSeeTableRecords([$confirmed, $none, $ems])
            ->filterTable('urgent_care', 'ems')
            ->assertCanSeeTableRecords([$ems])
            ->assertCanNotSeeTableRecords([$toCheck, $confirmed, $none]);
    }

    public function test_the_feedback_report_shows_helpful_share_reasons_and_drop_off(): void
    {
        $recorder = app(FeedbackRecorder::class);
        $recorder->vote('guide:kako-do-uput', true);
        $recorder->vote('guide:kako-do-uput', true);
        $recorder->vote('guide:kako-do-uput', true);
        $recorder->vote('guide:kako-do-uput', false);
        $recorder->reasons('guide:kako-do-uput', false, ['outdated']);
        $recorder->step('guidance:headache', 'start', 0);
        $recorder->step('guidance:headache', 'start', 0);
        $recorder->step('guidance:headache', 'q-red-flags', 1);

        Livewire::actingAs($this->admin())
            ->test(FeedbackReport::class)
            ->assertOk()
            ->assertSee('guide:kako-do-uput')
            ->assertSee('75 %')
            ->assertSee('Застарено: 1')
            ->assertSee('guidance:headache')
            ->assertSee('q-red-flags')
            ->assertSee('50 %');
    }

    public function test_the_feedback_report_needs_analytics_view(): void
    {
        $support = User::factory()->create(['user_kind' => UserKind::Staff]);

        $this->actingAs($support);

        $this->assertFalse(FeedbackReport::canAccess());
        $this->actingAs($this->admin());
        $this->assertTrue(FeedbackReport::canAccess());
    }
}
