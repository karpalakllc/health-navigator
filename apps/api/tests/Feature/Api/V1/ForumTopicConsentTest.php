<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\ForumTopics\Pages\ViewForumTopic;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The single consent checkbox on the new-topic form is recorded with the
 * topic so moderators can see when the author agreed; it is never public.
 */
class ForumTopicConsentTest extends TestCase
{
    use RefreshDatabase;

    private function createTopicAsMember(): ForumTopic
    {
        ForumCategory::factory()->create(['slug' => 'general']);
        SiteSetting::current()->update(['forum_topics_require_moderation' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'Consent recorded topic',
            'body' => 'This is the opening post body with enough length.',
            'accepted_community_rules' => true,
        ])->assertCreated();

        return ForumTopic::query()->sole();
    }

    public function test_topic_creation_records_when_consent_was_given(): void
    {
        Carbon::setTestNow('2026-10-06 09:30:00');

        $topic = $this->createTopicAsMember();

        $this->assertNotNull($topic->community_rules_accepted_at);
        $this->assertTrue($topic->community_rules_accepted_at->equalTo(Carbon::parse('2026-10-06 09:30:00')));
    }

    public function test_factory_and_staff_topics_leave_consent_time_empty(): void
    {
        $this->assertNull(ForumTopic::factory()->create()->community_rules_accepted_at);
    }

    public function test_consent_time_is_not_in_public_or_own_topic_json(): void
    {
        $topic = $this->createTopicAsMember();
        $responses = [
            $this->postJson('/api/v1/forum/categories/general/topics', [
                'title' => 'Another consent topic',
                'body' => 'This is the opening post body with enough length.',
                'accepted_community_rules' => true,
            ]),
            $this->getJson('/api/v1/forum/categories/general/topics'),
            $this->getJson("/api/v1/forum/categories/general/topics/{$topic->slug}"),
            $this->getJson('/api/v1/forum/topics/recent'),
            $this->getJson('/api/v1/forum/topics?q=consent'),
            $this->getJson('/api/v1/me/forum/topics'),
        ];

        foreach ($responses as $response) {
            $response->assertSuccessful();
            $this->assertStringNotContainsString('community_rules_accepted_at', $response->getContent());
            $this->assertStringNotContainsString('rules_accepted', $response->getContent());
        }
    }

    public function test_admin_topic_view_shows_consent_time(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $topic = ForumTopic::factory()->create([
            'community_rules_accepted_at' => Carbon::parse('2026-10-06 09:30:00'),
        ]);
        $untracked = ForumTopic::factory()->create();

        $admin = User::factory()->staff()->create();
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);

        Livewire::test(ViewForumTopic::class, ['record' => $topic->getRouteKey()])
            ->assertSee('Consent accepted at')
            ->assertSee('Oct 6, 2026');

        Livewire::test(ViewForumTopic::class, ['record' => $untracked->getRouteKey()])
            ->assertSee('Consent accepted at')
            ->assertSee('Not recorded');
    }
}
