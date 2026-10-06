<?php

namespace Tests\Feature\Filament;

use App\Enums\RemovalCategory;
use App\Enums\ReportReason;
use App\Enums\UserKind;
use App\Filament\Resources\ContentReports\Pages\ListContentReports;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Hiding from the report queue sets the public removal category: it starts
 * from the most common report reason and the moderator can change it.
 */
class ReportHidePublicReasonTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        Mail::fake();

        $this->moderator = User::factory()->create(['user_kind' => UserKind::Staff]);
        $this->moderator->syncRoles([RoleCatalog::MODERATOR]);
    }

    /**
     * @param  list<ReportReason>  $reasons
     * @return array{Review, ContentReport}
     */
    private function reported(array $reasons): array
    {
        $review = Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::factory(),
        ]);

        $reports = array_map(fn (ReportReason $reason) => ContentReport::factory()->about($review)->create(['reason' => $reason]), $reasons);

        return [$review, $reports[0]];
    }

    public function test_the_public_reason_defaults_to_the_most_common_report_reason(): void
    {
        [$review, $report] = $this->reported([ReportReason::Spam, ReportReason::PersonalData, ReportReason::PersonalData]);
        $this->actingAs($this->moderator);

        Livewire::test(ListContentReports::class)
            ->callTableAction('hide', $report, ['note' => 'Лични податоци за друго лице.']);

        $review->refresh();
        $this->assertSame(RemovalCategory::PersonalData, $review->removal_category);
        $this->assertNotNull($review->removed_at);
    }

    public function test_the_moderator_can_pick_another_public_reason(): void
    {
        [$review, $report] = $this->reported([ReportReason::Other]);
        $this->actingAs($this->moderator);

        Livewire::test(ListContentReports::class)
            ->callTableAction('hide', $report, ['note' => 'Незаконска содржина.', 'removal_category' => 'illegal']);

        $this->assertSame(RemovalCategory::Illegal, $review->fresh()->removal_category);
    }
}
