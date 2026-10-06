<?php

namespace Tests\Feature\Console;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\E2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class E2ESeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_to_run_in_a_deployed_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->runSeederDirectly();
            $this->fail('E2ESeeder ran in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('E2ESeeder only runs in', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_seed_local_demo_does_not_unlock_it(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config(['zdravje.seed.local_demo' => true]);

        $this->expectException(RuntimeException::class);

        $this->runSeederDirectly();
    }

    /** Not through db:seed, which stops to confirm in production. */
    private function runSeederDirectly(): void
    {
        $this->app->make(E2ESeeder::class)->setContainer($this->app)->run();
    }

    public function test_seeds_the_fixtures_the_browser_suite_asserts_on(): void
    {
        $this->seed(E2ESeeder::class);

        $admin = User::query()->where('email', E2ESeeder::ADMIN_EMAIL)->firstOrFail();
        $this->assertTrue($admin->hasRole('Administrator'));
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check(E2ESeeder::PASSWORD, $admin->password));

        $this->assertTrue(
            User::query()->where('email', E2ESeeder::STAFF_MODERATOR_EMAIL)->firstOrFail()->hasRole('Moderator'),
        );

        $communityModerator = User::query()->where('email', E2ESeeder::COMMUNITY_MODERATOR_EMAIL)->firstOrFail();
        $published = ForumCategory::query()->where('slug', E2ESeeder::FORUM_CATEGORY_SLUG)->firstOrFail();
        $hidden = ForumCategory::query()->where('slug', E2ESeeder::HIDDEN_FORUM_CATEGORY_SLUG)->firstOrFail();
        $this->assertTrue($communityModerator->hasRole('Forum Moderator'));
        $this->assertTrue($communityModerator->canModerateForumCategory($published));
        $this->assertFalse($communityModerator->canModerateForumCategory($hidden));

        foreach (E2ESeeder::MUTABLE_MEMBER_PREFIXES as $prefix) {
            for ($attempt = 0; $attempt < E2ESeeder::ATTEMPTS; $attempt++) {
                $this->assertTrue(User::query()->where('email', "{$prefix}-{$attempt}@e2e.test")->exists());
            }
        }

        $this->assertFalse($hidden->is_published);
        $this->assertSame(
            ForumContentStatus::Approved,
            ForumTopic::query()->where('slug', E2ESeeder::HIDDEN_FORUM_TOPIC_SLUG)->firstOrFail()->status,
        );

        $doctor = Doctor::query()->where('slug', E2ESeeder::DOCTOR_SLUG)->firstOrFail();
        $this->assertTrue($doctor->is_published);
        $this->assertSame(
            ReviewStatus::Pending,
            Review::query()->whereMorphedTo('reviewable', $doctor)->firstOrFail()->status,
        );

        $settings = SiteSetting::current();
        $this->assertTrue($settings->public_guidance);
        $this->assertTrue($settings->public_forum);
        $this->assertFalse($settings->public_pharmacies);
        $this->assertFalse($settings->public_products);
        $this->assertTrue($settings->forum_topics_require_moderation);
        $this->assertFalse($settings->forum_posts_require_moderation);
    }
}
