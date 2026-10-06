<?php

namespace Tests\Feature\Api\V1\Usernames;

use App\Actions\AnonymiseUser;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Support\Usernames\UsernameValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Changing a username on the account page: once in 90 days, the old name held
 * back from everyone else for six months; and the first-sign-in choice for
 * accounts that still have a temporary „clen-…“ name.
 */
class UsernameChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::current();
        $this->forgetRateLimits();
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['name' => 'Марија Костовска', 'username' => 'bitolchanka', ...$attributes]);
    }

    public function test_me_exposes_the_username_and_the_choice_flag(): void
    {
        Sanctum::actingAs($this->member());

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.username', 'bitolchanka')
            ->assertJsonPath('data.user.must_choose_username', false)
            ->assertJsonPath('data.user.username_change_available_at', null)
            // Kept as an alias for older clients; never the real name.
            ->assertJsonPath('data.user.display_name', 'bitolchanka')
            ->assertJsonPath('data.user.name', 'Марија Костовска');
    }

    public function test_a_member_changes_their_username_once_in_90_days(): void
    {
        $member = $this->member();
        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/me/profile', ['username' => ' Marija.Bt '])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'Marija.Bt')
            ->assertJsonPath('data.user.username_change_available_at', now()->addDays(90)->startOfSecond()->toIso8601String());

        $this->travel(89)->days();
        $this->withHeader('Accept-Language', 'mk')
            ->patchJson('/api/v1/me/profile', ['username' => 'marija_nova'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'еднаш на 90 дена']);

        // Saving the same name again is not a change.
        $this->patchJson('/api/v1/me/profile', ['username' => 'Marija.Bt'])->assertOk();

        $this->travel(2)->days();
        $this->patchJson('/api/v1/me/profile', ['username' => 'marija_nova'])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'marija_nova');

        $this->assertSame(['bitolchanka', 'Marija.Bt'], UsernameHistory::query()->orderBy('id')->pluck('username')->all());
    }

    public function test_a_released_username_is_reserved_for_six_months(): void
    {
        $member = $this->member();
        Sanctum::actingAs($member);
        $this->patchJson('/api/v1/me/profile', ['username' => 'marija_bt'])->assertOk();

        $history = UsernameHistory::query()->sole();
        $this->assertSame('changed', $history->reason);
        $this->assertSame($member->id, $history->user_id);

        // Nobody else can take it, in any spelling…
        foreach (['bitolchanka', 'Битолчанка', 'B1tolchanka'] as $spelling) {
            $this->assertSame('taken', UsernameValidator::problem($spelling), $spelling);
        }

        // …but the member who gave it up may take it back.
        $this->assertNull(UsernameValidator::problem('bitolchanka', $member->fresh()));

        $this->travel(6)->months();
        $this->travel(1)->days();
        $this->assertNull(UsernameValidator::problem('bitolchanka'));

        Artisan::call('model:prune', ['--model' => [UsernameHistory::class]]);
        $this->assertDatabaseCount('username_history', 0);
    }

    public function test_the_change_validates_like_registration(): void
    {
        User::factory()->create(['username' => 'taken_name']);
        $member = $this->member();
        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/me/profile', ['username' => 'Taken.Name'])->assertJsonValidationErrors('username');
        $this->patchJson('/api/v1/me/profile', ['username' => 'moderator_ana'])->assertJsonValidationErrors('username');
        $this->patchJson('/api/v1/me/profile', [])->assertJsonValidationErrors('username');

        $this->assertSame('bitolchanka', $member->fresh()->username);
        $this->assertDatabaseCount('username_history', 0);
    }

    public function test_choosing_a_first_username_replaces_the_temporary_one_without_a_cooldown(): void
    {
        $member = User::factory()->create(['username' => null]);
        $this->assertStringStartsWith('clen-', $member->username);
        $this->assertTrue($member->must_choose_username);
        $temporary = $member->username;

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/me')
            ->assertJsonPath('data.user.must_choose_username', true)
            ->assertJsonPath('data.user.username', $temporary);

        $this->patchJson('/api/v1/me/profile', ['username' => 'ana_od_ohrid'])
            ->assertOk()
            ->assertJsonPath('data.user.must_choose_username', false)
            ->assertJsonPath('data.user.username_change_available_at', null);

        // A placeholder nobody chose is not worth holding back.
        $this->assertDatabaseCount('username_history', 0);

        $this->patchJson('/api/v1/me/profile', ['username' => 'ana_ohrid'])->assertOk();
        $this->patchJson('/api/v1/me/profile', ['username' => 'ana_oh'])->assertUnprocessable();
    }

    public function test_posting_waits_for_a_chosen_username_but_reading_does_not(): void
    {
        $member = User::factory()->create(['username' => null]);
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'slug' => 'water']);
        $review = Review::factory()->approved()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        Sanctum::actingAs($member);

        $message = __('api.username.required', [], 'mk');

        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5, 'body' => 'Внимателна и јасна докторка.'])
            ->assertForbidden()
            ->assertJsonPath('message', $message);
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/forum/categories/general/topics', ['title' => 'Прашање за вода', 'body' => 'Колку вода треба да се пие во текот на денот?', 'accepted_community_rules' => true])
            ->assertForbidden()
            ->assertJsonPath('message', $message);
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/forum/categories/general/topics/water/posts', ['body' => 'Јас пијам осум чаши дневно.'])
            ->assertForbidden()
            ->assertJsonPath('message', $message);
        $this->putJson("/api/v1/reviews/{$review->id}/helpful")->assertForbidden();

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertOk();
        $this->getJson('/api/v1/forum/categories/general/topics/water')->assertOk();
        $this->getJson('/api/v1/me')->assertOk();

        $this->patchJson('/api/v1/me/profile', ['username' => 'ana_od_ohrid'])->assertOk();

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5, 'body' => 'Внимателна и јасна докторка.'])
            ->assertCreated();
    }

    public function test_a_deleted_account_frees_nothing_for_six_months(): void
    {
        $member = $this->member();
        app(AnonymiseUser::class)->handle($member);

        $member->refresh();
        $this->assertNull($member->username);
        $this->assertSame(__('api.account.deleted_user_name'), $member->publicName());

        $held = UsernameHistory::query()->sole();
        $this->assertNull($held->user_id);
        $this->assertSame('anonymised', $held->reason);
        $this->assertSame('taken', UsernameValidator::problem('bitolchanka'));
    }
}
