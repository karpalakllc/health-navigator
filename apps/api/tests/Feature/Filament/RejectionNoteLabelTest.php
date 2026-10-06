<?php

namespace Tests\Feature\Filament;

use App\Enums\ReviewStatus;
use App\Filament\Resources\ContentReports\Pages\ListContentReports;
use App\Filament\Resources\ForumPosts\Pages\ListForumPosts;
use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Filament\Support\ModerationBulkActions;
use App\Models\ContentReport;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Forms\Components\Textarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Rejection notes are emailed to the author and returned by the My* API
 * resources; the moderation forms used to call them "internal".
 */
class RejectionNoteLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();

        $admin = User::factory()->staff()->create();
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);
    }

    private function labelledForTheAuthor(Textarea $field): bool
    {
        return str_contains((string) $field->getLabel(), 'shown to the author')
            && ! str_contains((string) $field->getLabel(), 'internal');
    }

    public function test_row_reject_forms_say_the_note_reaches_the_author(): void
    {
        $review = Review::factory()->create();
        $topic = ForumTopic::factory()->pending()->create();
        $post = ForumPost::factory()->pending()->create();

        foreach ([[ListReviews::class, $review], [ListForumTopics::class, $topic], [ListForumPosts::class, $post]] as [$page, $record]) {
            Livewire::test($page)
                ->mountTableAction('reject', $record)
                ->assertFormFieldExists('rejection_note', $this->labelledForTheAuthor(...));
        }
    }

    public function test_bulk_and_page_reject_forms_say_the_note_reaches_the_author(): void
    {
        $review = Review::factory()->create();

        Livewire::test(ListReviews::class)
            ->mountTableBulkAction('reject_selected', [$review])
            ->assertFormFieldExists('rejection_note', $this->labelledForTheAuthor(...));

        Livewire::test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->mountAction('reject')
            ->assertFormFieldExists('rejection_note', $this->labelledForTheAuthor(...));
    }

    /**
     * The terms promise the author the reason, so the reason cannot be left
     * out: it is required and starts from a general one.
     */
    public function test_a_bulk_rejection_needs_a_reason_and_starts_from_a_general_one(): void
    {
        $review = Review::factory()->create();

        Livewire::test(ListReviews::class)
            ->mountTableBulkAction('reject_selected', [$review])
            ->assertTableBulkActionDataSet(['rejection_note' => ModerationBulkActions::PRESET_REASONS[0]])
            ->setTableBulkActionData(['rejection_note' => ''])
            ->callMountedTableBulkAction()
            ->assertHasTableBulkActionErrors(['rejection_note' => 'required']);

        $this->assertSame(ReviewStatus::Pending, $review->fresh()->status);
    }

    public function test_hiding_reported_content_needs_a_reason_and_a_picked_one_is_sent(): void
    {
        Mail::fake();
        $review = Review::factory()->approved()->create();
        $report = ContentReport::factory()->about($review)->create();

        Livewire::test(ListContentReports::class)
            ->callTableAction('hide', $report, ['note' => ''])
            ->assertHasTableActionErrors(['note' => 'required']);
        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);

        Livewire::test(ListContentReports::class)
            ->mountTableAction('hide', $report)
            ->setTableActionData(['note_preset' => ModerationBulkActions::PRESET_REASONS[2]])
            ->assertTableActionDataSet(['note' => ModerationBulkActions::PRESET_REASONS[2]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(ModerationBulkActions::PRESET_REASONS[2], $review->fresh()->rejection_note);
    }
}
