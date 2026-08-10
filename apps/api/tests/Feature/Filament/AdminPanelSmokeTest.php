<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The admin panel had no automated coverage at all, which made the Filament
 * major upgrade unverifiable — the API suite passes whether or not a single
 * admin page renders.
 *
 * This is deliberately a smoke test: it asserts each page boots and returns 200
 * for an authorised user, which is what a framework upgrade breaks. It does not
 * assert on panel behaviour.
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
        ]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    public function test_the_login_page_is_reachable_anonymously(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_the_panel_redirects_anonymous_visitors_to_login(): void
    {
        $this->get('/admin')->assertRedirectContains('/admin/login');
    }

    public function test_the_dashboard_renders_for_an_administrator(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk();
    }

    /**
     * @return list<array{string}>
     */
    public static function resourceIndexes(): array
    {
        return [
            ['/admin/doctors'],
            ['/admin/facilities'],
            ['/admin/specialties'],
            ['/admin/departments'],
            ['/admin/procedures'],
            ['/admin/languages'],
            ['/admin/clinical-interests'],
            ['/admin/products'],
            ['/admin/reviews'],
            ['/admin/forum-categories'],
            ['/admin/forum-topics'],
            ['/admin/forum-posts'],
            ['/admin/triage-flows'],
            ['/admin/roles'],
        ];
    }

    #[DataProvider('resourceIndexes')]
    public function test_resource_index_pages_render(string $path): void
    {
        $this->actingAs($this->admin())->get($path)->assertOk();
    }

    public function test_resource_create_pages_render(): void
    {
        $admin = $this->admin();

        foreach (['/admin/doctors/create', '/admin/facilities/create', '/admin/specialties/create'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_a_resource_edit_page_renders(): void
    {
        $admin = $this->admin();
        $category = ForumCategory::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/forum-categories/{$category->getKey()}/edit")
            ->assertOk();
    }

    public function test_the_site_settings_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/manage-site-settings')
            ->assertOk();
    }

    public function test_a_member_cannot_reach_the_panel(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
        ]);

        $this->actingAs($member)->get('/admin')->assertForbidden();
    }
}
