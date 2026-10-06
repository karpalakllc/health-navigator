<?php

namespace Database\Seeders;

use App\Enums\FacilityType;
use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use App\Support\DeploymentEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Fixed fixtures for the Playwright suite (apps/web/e2e). Run after a fresh
 * migration: `php artisan migrate:fresh --seed --seeder=E2ESeeder`.
 *
 * Every value the browser specs assert on is a constant here, mirrored in
 * apps/web/e2e/fixtures.ts — change both together.
 *
 * Unlike the demo seeders, SEED_LOCAL_DEMO does not unlock this one: it creates
 * staff accounts with a published password, so it refuses to run anywhere
 * DeploymentEnvironment counts as deployed, and it throws rather than warns so
 * a misconfigured E2E run fails instead of testing an empty database.
 *
 * Accounts that a spec changes (a password reset, a review, a new topic) come
 * in numbered copies, one per Playwright attempt, so a retried test starts from
 * an untouched account instead of the state its failed attempt left behind.
 */
class E2ESeeder extends Seeder
{
    /** Satisfies Password::defaults(): 10+ characters, letters and numbers. */
    public const PASSWORD = 'E2eLozinka2026';

    public const ADMIN_EMAIL = 'admin@e2e.test';

    public const STAFF_MODERATOR_EMAIL = 'moderator@e2e.test';

    public const COMMUNITY_MODERATOR_EMAIL = 'forum-moderator@e2e.test';

    public const MEMBER_EMAIL = 'member@e2e.test';

    /**
     * Base32 TOTP secret for the staff accounts, for when the admin panel
     * requires app authentication (staff 2FA). The admin spec derives the
     * current code from it (apps/web/e2e/support/totp.ts).
     */
    public const STAFF_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    /** Copies per mutable account: Playwright's retry index is 0 or 1 (CI retries once). */
    public const ATTEMPTS = 3;

    /** @var list<string> each becomes "{prefix}-{attempt}@e2e.test" */
    public const MUTABLE_MEMBER_PREFIXES = ['reviewer', 'forum', 'reset'];

    public const SPECIALTY_SLUG = 'e2e-kardiologija';

    public const DOCTOR_SLUG = 'e2e-ana-testovska';

    public const DOCTOR_NAME = 'д-р Ана Тестовска';

    public const FACILITY_SLUG = 'e2e-klinika-centar';

    public const PHARMACY_SLUG = 'e2e-apteka-centar';

    public const PRODUCT_SLUG = 'e2e-paracetamol-500';

    public const FORUM_CATEGORY_SLUG = 'e2e-opshto-zdravje';

    public const HIDDEN_FORUM_CATEGORY_SLUG = 'e2e-skriena-kategorija';

    public const FORUM_TOPIC_SLUG = 'e2e-dobredojdovte';

    public const FORUM_TOPIC_TITLE = 'Добредојдовте во E2E заедницата';

    public const HIDDEN_FORUM_TOPIC_SLUG = 'e2e-skriena-tema';

    /** One distinctive word, so a search that leaks the topic cannot match anything else. */
    public const HIDDEN_FORUM_TOPIC_TITLE = 'Ксилофонска тема во скриена категорија';

    public function run(): void
    {
        if (DeploymentEnvironment::isDeployed()) {
            throw new RuntimeException(
                'E2ESeeder only runs in '.implode(', ', DeploymentEnvironment::NON_DEPLOYED)
                .' (APP_ENV='.app()->environment().'). It seeds accounts with a known password.'
            );
        }

        $this->seedSiteSettings();
        $this->seedUsers();
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(TriageSeeder::class);
        $this->seedDirectory();
        $this->seedForum();
        $this->seedReviews();
    }

    /**
     * Launch defaults (SiteSetting::defaults()) with guidance switched on for the
     * triage spec. Pharmacies and products stay off: the module-gating spec
     * asserts on pharmacies being unavailable.
     */
    private function seedSiteSettings(): void
    {
        SiteSetting::current()->update([
            'registrations_enabled' => true,
            'maintenance_mode' => false,
            'public_guidance' => true,
            'public_products' => false,
            'public_pharmacies' => false,
            'public_forum' => true,
            'forum_rules_enabled' => false,
            'forum_topics_require_moderation' => true,
            // Replies from members publish immediately so the forum spec can see
            // one appear; new topics still go through moderation.
            'forum_posts_require_moderation' => false,
        ]);
    }

    private function seedUsers(): void
    {
        $staff = [
            $this->user(self::ADMIN_EMAIL, 'E2E Администратор', UserRole::Admin, UserKind::Staff),
            $this->user(self::STAFF_MODERATOR_EMAIL, 'E2E Модератор', UserRole::Moderator, UserKind::Staff),
        ];

        // Only once the schema has app authentication. Set through the model so
        // the attribute's encrypted cast applies.
        if (Schema::hasColumn('users', 'app_authentication_secret')) {
            foreach ($staff as $user) {
                $user->forceFill(['app_authentication_secret' => self::STAFF_TOTP_SECRET])->save();
            }
        }
        $this->user(self::COMMUNITY_MODERATOR_EMAIL, 'E2E Форум модератор', UserRole::Member, UserKind::Client);
        $this->user(self::MEMBER_EMAIL, 'E2E Член', UserRole::Member, UserKind::Client);

        foreach (self::MUTABLE_MEMBER_PREFIXES as $prefix) {
            for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
                $this->user("{$prefix}-{$attempt}@e2e.test", "E2E {$prefix} {$attempt}", UserRole::Member, UserKind::Client);
            }
        }
    }

    private function user(string $email, string $name, UserRole $role, UserKind $kind): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'role' => $role,
                'user_kind' => $kind,
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedDirectory(): void
    {
        $specialty = Specialty::query()->updateOrCreate(
            ['slug' => self::SPECIALTY_SLUG],
            [
                'name' => 'Кардиологија',
                'description' => 'Срцеви и васкуларни заболувања.',
                'sort_order' => 1,
                'is_published' => true,
            ],
        );

        $facility = Facility::query()->updateOrCreate(
            ['slug' => self::FACILITY_SLUG],
            [
                'name' => 'Клиника Центар',
                'type' => FacilityType::Clinic,
                'description' => 'Приватна клиника за E2E тестови.',
                'city' => 'Скопје',
                'address' => 'ул. Македонија 10',
                'phone' => '+389 2 300 0000',
                'email' => 'klinika@e2e.test',
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $doctor = Doctor::query()->updateOrCreate(
            ['slug' => self::DOCTOR_SLUG],
            [
                'full_name' => self::DOCTOR_NAME,
                'title' => 'д-р',
                'bio' => 'Кардиолог со долгогодишно искуство. Профил за E2E тестови.',
                'city' => 'Скопје',
                'phone' => '+389 70 000 001',
                'email' => 'ana@e2e.test',
                'accepts_new_patients' => true,
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $doctor->specialties()->sync([$specialty->id => ['is_primary' => true]]);
        $doctor->facilities()->sync([$facility->id => ['is_primary' => true]]);

        $pharmacy = Facility::query()->updateOrCreate(
            ['slug' => self::PHARMACY_SLUG],
            [
                'name' => 'Аптека Центар',
                'type' => FacilityType::Pharmacy,
                'description' => 'Аптека за E2E тестови.',
                'city' => 'Скопје',
                'address' => 'ул. Македонија 12',
                'phone' => '+389 2 300 0001',
                'email' => 'apteka@e2e.test',
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $product = Product::query()->updateOrCreate(
            ['slug' => self::PRODUCT_SLUG],
            [
                'name' => 'Парацетамол 500 mg',
                'description' => 'Аналгетик и антипиретик.',
                'category' => 'Аналгетици',
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $pharmacy->products()->syncWithoutDetaching([
            $product->id => [
                'price' => 120,
                'currency' => 'MKD',
                'is_available' => true,
                'price_updated_at' => now(),
            ],
        ]);
    }

    private function seedForum(): void
    {
        $member = User::query()->where('email', self::MEMBER_EMAIL)->firstOrFail();

        $published = ForumCategory::query()->updateOrCreate(
            ['slug' => self::FORUM_CATEGORY_SLUG],
            [
                'name' => 'Општо здравје',
                'description' => 'Општа дискусија (само информативно).',
                'sort_order' => 1,
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $hidden = ForumCategory::query()->updateOrCreate(
            ['slug' => self::HIDDEN_FORUM_CATEGORY_SLUG],
            [
                'name' => 'Скриена категорија',
                'description' => 'Необјавена категорија — никогаш не смее да се види јавно.',
                'sort_order' => 2,
                'is_published' => false,
                'published_at' => null,
            ],
        );

        $welcome = ForumTopic::query()->updateOrCreate(
            ['forum_category_id' => $published->id, 'slug' => self::FORUM_TOPIC_SLUG],
            [
                'user_id' => $member->id,
                'title' => self::FORUM_TOPIC_TITLE,
                'body' => 'Одобрена тема за E2E тестови. Темите и одговорите се модерираат.',
                'status' => ForumContentStatus::Approved,
                'published_at' => now()->subHour(),
            ],
        );

        ForumPost::query()->updateOrCreate(
            ['forum_topic_id' => $welcome->id, 'user_id' => $member->id],
            [
                'body' => 'Прв одобрен одговор во темата.',
                'status' => ForumContentStatus::Approved,
                'published_at' => now()->subMinutes(30),
            ],
        );

        $welcome->update(['replies_count' => 1, 'last_post_at' => now()->subMinutes(30)]);

        // Approved, so the only thing keeping it off the public site is its
        // category's publication state.
        ForumTopic::query()->updateOrCreate(
            ['forum_category_id' => $hidden->id, 'slug' => self::HIDDEN_FORUM_TOPIC_SLUG],
            [
                'user_id' => $member->id,
                'title' => self::HIDDEN_FORUM_TOPIC_TITLE,
                'body' => 'Оваа тема е во необјавена категорија.',
                'status' => ForumContentStatus::Approved,
                'published_at' => now()->subHour(),
            ],
        );

        $communityModerator = User::query()->where('email', self::COMMUNITY_MODERATOR_EMAIL)->firstOrFail();
        $communityModerator->syncRoles(['Forum Moderator']);
        $communityModerator->moderatedForumCategories()->sync([$published->id]);
    }

    private function seedReviews(): void
    {
        $doctor = Doctor::query()->where('slug', self::DOCTOR_SLUG)->firstOrFail();
        $member = User::query()->where('email', self::MEMBER_EMAIL)->firstOrFail();

        Review::query()->updateOrCreate(
            ['user_id' => $member->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id],
            [
                'rating' => 4,
                'body' => 'Рецензија што чека модерација (E2E).',
                'status' => ReviewStatus::Pending,
                'published_at' => null,
            ],
        );
    }
}
