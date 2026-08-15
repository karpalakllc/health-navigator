<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Resources\ForumCategories\Pages\CreateForumCategory;
use App\Filament\Resources\ForumCategories\Pages\EditForumCategory;
use App\Models\ForumCategory;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The admin panel had no automated coverage at all, which made the Filament
 * major upgrade unverifiable — the API suite passes whether or not a single
 * admin page renders.
 *
 * Two layers, because a framework upgrade can break either one:
 *
 *  - every registered page boots and returns 200 for an authorised user, with
 *    the page list taken from the panel rather than hand-maintained;
 *  - forms actually submit, save, and reject invalid input, which is where a
 *    Livewire major breaks and where a page load proves nothing.
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
     * Every page the panel registers, asked for by the panel rather than listed
     * here.
     *
     * A hand-maintained list silently falls behind: this one had drifted to 14 of
     * 18 pages, and the four it had lost were the ones most worth covering —
     * analytics-overview (the only widget page) and the two user resources, which
     * are the whole surface of the spatie/laravel-permission major. Deriving the
     * list means adding a resource adds its coverage.
     *
     * Slugs are taken from the panel too. Several are not guessable — the client
     * and staff resources live at /admin/clients/client-users and
     * /admin/staff/staff-users, and /admin/clients on its own is a 404.
     *
     * @return list<string>
     */
    private function registeredPageUrls(): array
    {
        $panel = Filament::getPanel('admin');
        $urls = [];

        foreach ($panel->getResources() as $resource) {
            $urls[] = $resource::getUrl('index', panel: 'admin');
        }

        foreach ($panel->getPages() as $page) {
            $urls[] = $page::getUrl(panel: 'admin');
        }

        return $urls;
    }

    public function test_every_registered_page_renders(): void
    {
        $admin = $this->admin();
        $urls = $this->registeredPageUrls();

        // Guard against the enumeration silently returning nothing and the test
        // passing by asserting on an empty loop.
        $this->assertGreaterThanOrEqual(18, count($urls));

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /**
     * Pins the four pages the hand-written list had lost. Without this, the
     * enumeration above could quietly stop reaching them again and still pass.
     */
    public function test_the_enumeration_reaches_the_previously_uncovered_pages(): void
    {
        $urls = array_map(
            static fn (string $url): string => parse_url($url, PHP_URL_PATH) ?: $url,
            $this->registeredPageUrls(),
        );

        foreach ([
            '/admin/pharmacies',
            '/admin/analytics-overview',
            '/admin/clients/client-users',
            '/admin/staff/staff-users',
        ] as $path) {
            $this->assertContains($path, $urls);
        }
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

    /**
     * Submits a form for real, rather than only asking whether the page renders.
     *
     * A GET returns 200 whatever the interaction layer is doing, so it cannot
     * speak to the Livewire major in this PR — the breaking changes there are
     * almost entirely in property binding, actions, and validation, none of
     * which a page load exercises.
     */
    public function test_a_resource_form_creates_a_record(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateForumCategory::class)
            ->fillForm([
                'name' => 'Кардиологија',
                'slug' => 'kardiologija',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('forum_categories', [
            'slug' => 'kardiologija',
            'is_published' => true,
        ]);
    }

    public function test_a_resource_form_saves_an_edit(): void
    {
        $this->actingAs($this->admin());
        $category = ForumCategory::factory()->create(['name' => 'Before']);

        Livewire::test(EditForumCategory::class, ['record' => $category->getKey()])
            ->fillForm(['name' => 'After'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('After', $category->refresh()->name);
    }

    /** Validation still rejects bad input rather than silently accepting it. */
    public function test_a_resource_form_reports_validation_errors(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateForumCategory::class)
            ->fillForm(['name' => '', 'slug' => ''])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertDatabaseCount('forum_categories', 0);
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
