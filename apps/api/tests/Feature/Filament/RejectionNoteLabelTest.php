<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Resources\ForumPosts\Pages\ListForumPosts;
use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Forms\Components\Textarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $admin = User::factory()->create(['role' => UserRole::Admin, 'user_kind' => UserKind::Staff]);
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
}
