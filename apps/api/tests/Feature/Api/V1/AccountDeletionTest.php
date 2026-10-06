<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ForumContentStatus;
use App\Enums\ReportReason;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Mail\AccountExistsMail;
use App\Models\AnalyticsEvent;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Support\ReviewHelpfulVotes;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * D5: a member deletes their account by re-entering their password. The row is
 * anonymised in place — personal data cleared, address freed, every session
 * ended — and their public reviews and forum posts stay, shown as a deleted user.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'sufficiently1long';

    private string $token;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        $this->forgetRateLimits();
        Storage::fake(config('media.disk'));

        $this->member = User::factory()->create([
            'name' => 'Марија Костовска',
            'display_name' => 'Марија К.',
            'email' => 'marija@example.com',
            'password' => self::PASSWORD,
        ]);
        $this->token = $this->member->createToken('Firefox · Linux')->plainTextToken;
    }

    private function as(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, $uri, $data);
    }

    private function deleteAccount(string $password = self::PASSWORD): TestResponse
    {
        return $this->as($this->token, 'DELETE', '/api/v1/me', ['password' => $password]);
    }

    public function test_a_wrong_or_missing_password_changes_nothing(): void
    {
        $this->deleteAccount('not-my-password1')
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', __('api.account.password_incorrect'));

        $this->as($this->token, 'DELETE', '/api/v1/me')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->member->refresh();
        $this->assertFalse($this->member->isAnonymised());
        $this->assertSame('marija@example.com', $this->member->email);
        $this->as($this->token, 'GET', '/api/v1/me')->assertOk();
    }

    public function test_deletion_clears_personal_data_and_ends_every_session(): void
    {
        $avatar = 'users/avatars/marija.webp';
        Storage::disk(config('media.disk'))->put($avatar, 'image');
        $category = ForumCategory::factory()->create();
        $this->member->forceFill(['avatar_path' => $avatar])->save();
        $this->member->assignRole(RoleCatalog::ensure(RoleCatalog::FORUM_MODERATOR));
        $this->member->moderatedForumCategories()->attach($category);
        $otherDevice = $this->member->createToken('Safari · iOS')->plainTextToken;

        DB::table('sessions')->insert(['id' => 'panel-session', 'user_id' => $this->member->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => 'marija@example.com', 'token' => 'hashed', 'created_at' => now()]);
        $event = AnalyticsEvent::query()->create(['event' => 'user.login', 'user_id' => $this->member->id, 'occurred_at' => now()]);

        $this->deleteAccount()
            ->assertOk()
            ->assertJsonPath('data.message', __('api.account.deleted'));

        $user = $this->member->fresh();
        $this->assertTrue($user->isAnonymised());
        $this->assertSame('', $user->name);
        $this->assertNull($user->display_name);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->email_verified_at);
        $this->assertStringEndsWith('@deleted.invalid', $user->email);
        $this->assertStringNotContainsString('marija', $user->email);
        $this->assertSame(__('api.account.deleted_user_name'), $user->publicName());

        Storage::disk(config('media.disk'))->assertMissing($avatar);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame([], $user->getRoleNames()->all());
        $this->assertSame(0, DB::table('forum_category_moderator')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'marija@example.com')->count());
        $this->assertNull($event->fresh()->user_id);

        $this->as($this->token, 'GET', '/api/v1/me')->assertUnauthorized();
        $this->as($otherDevice, 'GET', '/api/v1/me')->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', ['email' => 'marija@example.com', 'password' => self::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('api.auth.invalid_credentials'));
    }

    public function test_public_pages_keep_the_content_under_a_deleted_user(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        Review::factory()->approved()->create([
            'user_id' => $this->member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'body' => 'Внимателна и јасна.',
        ]);
        $category = ForumCategory::factory()->create(['slug' => 'opsto']);
        $topic = ForumTopic::factory()->create([
            'user_id' => $this->member->id,
            'forum_category_id' => $category->id,
            'slug' => 'prasanje',
            'title' => 'Прашање за вакцини',
        ]);
        ForumPost::factory()->create(['user_id' => $this->member->id, 'forum_topic_id' => $topic->id, 'body' => 'Мој одговор']);

        $this->deleteAccount()->assertOk();

        $deleted = __('api.account.deleted_user_name');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.author_name', $deleted)
            ->assertJsonPath('data.0.body', 'Внимателна и јасна.')
            ->assertDontSee('Марија');

        $this->getJson('/api/v1/forum/categories/opsto/topics/prasanje')
            ->assertOk()
            ->assertJsonPath('data.topic.author_name', $deleted)
            ->assertJsonPath('data.topic.author.name', $deleted)
            ->assertJsonPath('data.topic.author.member_since', null)
            ->assertJsonPath('data.posts.0.author.name', $deleted)
            ->assertJsonPath('data.posts.0.body', 'Мој одговор')
            ->assertDontSee('Марија');

        $this->getJson('/api/v1/forum/categories/opsto/topics')
            ->assertOk()
            ->assertJsonPath('data.0.author_name', $deleted);

        $this->getJson('/api/v1/doctors/ana-petrovska')->assertOk();
    }

    public function test_the_freed_address_can_register_a_new_account(): void
    {
        Notification::fake();
        Mail::fake();

        $this->deleteAccount()->assertOk();
        $this->forgetRateLimits();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Марија Нова',
            'display_name' => 'Марија Н.',
            'email' => 'Marija@Example.com',
            'password' => 'another1longpassword',
            'password_confirmation' => 'another1longpassword',
        ])->assertStatus(202);

        $fresh = User::query()->where('email', 'marija@example.com')->sole();
        $this->assertNotSame($this->member->id, $fresh->id);
        $this->assertFalse($fresh->isAnonymised());
        Notification::assertSentTo($fresh, VerifyEmailNotification::class);
        Mail::assertNotQueued(AccountExistsMail::class);
    }

    /**
     * The privacy policy says only published content stays: what was still
     * waiting for moderation is withdrawn, so it can never be published under
     * a deleted account.
     */
    public function test_pending_content_is_withdrawn_and_published_content_stays(): void
    {
        Mail::fake();
        $category = ForumCategory::factory()->create();
        $pendingReview = Review::factory()->create(['user_id' => $this->member->id]);
        $publishedReview = Review::factory()->approved()->create(['user_id' => $this->member->id]);
        $pendingTopic = ForumTopic::factory()->pending()->create(['forum_category_id' => $category->id, 'user_id' => $this->member->id]);
        $publishedTopic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'user_id' => $this->member->id]);
        $pendingPost = ForumPost::factory()->pending()->create(['forum_topic_id' => $publishedTopic->id, 'user_id' => $this->member->id]);
        $othersPending = Review::factory()->create();

        $this->deleteAccount()->assertOk();

        $this->assertSame(ReviewStatus::Rejected, $pendingReview->fresh()->status);
        $this->assertSame(ForumContentStatus::Rejected, $pendingTopic->fresh()->status);
        $this->assertSame(ForumContentStatus::Rejected, $pendingPost->fresh()->status);
        $this->assertSame(ReviewStatus::Approved, $publishedReview->fresh()->status);
        $this->assertSame(ForumContentStatus::Approved, $publishedTopic->fresh()->status);
        $this->assertSame(ReviewStatus::Pending, $othersPending->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_report_notes_are_cleared_and_reports_and_votes_stay_unlinked_from_any_person(): void
    {
        $review = Review::factory()->approved()->create();
        $report = ContentReport::factory()->about($review)->create([
            'user_id' => $this->member->id,
            'reason' => ReportReason::PersonalData,
            'note' => 'Јас сум Марија, ова е мојата дијагноза.',
        ]);
        $othersReport = ContentReport::factory()->about($review)->create(['note' => 'Друга белешка']);
        ReviewHelpfulVotes::add($review, $this->member);

        $this->deleteAccount()->assertOk();

        $report->refresh();
        $this->assertNull($report->note);
        $this->assertSame(ReportReason::PersonalData, $report->reason);
        $this->assertSame('Друга белешка', $othersReport->fresh()->note);
        $this->assertSame(1, $review->fresh()->helpful_count);
    }

    public function test_staff_accounts_cannot_delete_themselves_through_the_api(): void
    {
        // Staff without admin.access may hold a token; admin.access holders cannot.
        $staff = User::factory()->staff()->create(['password' => self::PASSWORD]);
        $token = $staff->createToken('web')->plainTextToken;

        $this->as($token, 'DELETE', '/api/v1/me', ['password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('code', 'account.staff_cannot_delete');

        $this->assertFalse($staff->fresh()->isAnonymised());
    }

    public function test_password_guesses_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->deleteAccount('wrong-password'.$i)->assertUnprocessable();
        }

        $this->deleteAccount()->assertStatus(429);
        $this->assertFalse($this->member->fresh()->isAnonymised());
    }

    public function test_the_admin_panel_cannot_revive_a_deleted_account(): void
    {
        $this->deleteAccount()->assertOk();
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('update', $this->member->fresh()));
        $this->assertFalse($admin->can('suspend', $this->member->fresh()));

        $this->actingAs($admin);
        Livewire::test(EditClientUser::class, ['record' => $this->member->getKey()])
            ->assertForbidden();
    }

    public function test_deletion_requires_a_session(): void
    {
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/v1/me', ['password' => self::PASSWORD])->assertUnauthorized();
    }
}
