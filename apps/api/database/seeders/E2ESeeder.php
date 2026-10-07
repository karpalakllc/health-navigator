<?php

namespace Database\Seeders;

use App\Enums\FacilityType;
use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserKind;
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
use App\Support\RoleCatalog;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Database\Seeders\Concerns\SeedsUsernames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Fixed fixtures for the Playwright suite (apps/web/e2e). Run after a fresh
 * migration: `php artisan migrate:fresh --seed --seeder=E2ESeeder`.
 *
 * Every value the browser specs assert on is a constant here, mirrored in
 * apps/web/e2e/support/fixtures.ts — change both together.
 *
 * Unlike the demo seeders, SEED_LOCAL_DEMO does not unlock this one: it creates
 * staff accounts with a published password, so it refuses to run outside
 * `local` and `testing` (not even on a shared `development` box), and it throws rather than warns so
 * a misconfigured E2E run fails instead of testing an empty database.
 *
 * Accounts that a spec changes (a password reset, a review, a new topic) come
 * in numbered copies, one per Playwright attempt, so a retried test starts from
 * an untouched account instead of the state its failed attempt left behind.
 */
class E2ESeeder extends Seeder
{
    use SeedsUsernames;

    /** Satisfies Password::defaults(): 10+ characters, letters and numbers. */
    public const PASSWORD = 'E2eLozinka2026';

    public const ADMIN_EMAIL = 'admin@e2e.test';

    public const STAFF_MODERATOR_EMAIL = 'moderator@e2e.test';

    public const COMMUNITY_MODERATOR_EMAIL = 'forum-moderator@e2e.test';

    public const MEMBER_EMAIL = 'member@e2e.test';

    /**
     * Base32 TOTP secrets for the staff accounts' app authentication (staff
     * 2FA). The admin spec derives the current code from them
     * (apps/web/e2e/support/totp.ts).
     *
     * One per account, never shared: Filament's replay guard remembers the last
     * accepted 30-second step per secret, so two accounts on one secret could
     * not both sign in within the same step.
     */
    public const ADMIN_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const STAFF_MODERATOR_TOTP_SECRET = 'KRUGKIDROVUWG2ZA';

    /** Environments it may run in: a developer's machine and the test suite. */
    public const ENVIRONMENTS = ['local', 'testing'];

    /** Copies per mutable account: Playwright's retry index is 0 or 1 (CI retries once). */
    public const ATTEMPTS = 3;

    /** @var list<string> each becomes "{prefix}-{attempt}@e2e.test" */
    public const MUTABLE_MEMBER_PREFIXES = ['reviewer', 'forum', 'reset', 'reported', 'account', 'doctor', 'impact'];

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

    /**
     * Doctor accounts (e2e/doctor-claim.spec.ts): one unmanaged profile per
     * attempt, "{prefix}-{attempt}", for "doctor-{attempt}@e2e.test" to be
     * assigned to, with one published review to reply to.
     */
    public const DOCTOR_CLAIM_SLUG_PREFIX = 'e2e-doctor-claim';

    public const DOCTOR_CLAIM_NAME_PREFIX = 'д-р Петар Тестовски';

    public function run(): void
    {
        // Narrower than DeploymentEnvironment::NON_DEPLOYED: a shared
        // `development` box is reachable by others, and this publishes a password.
        if (! app()->environment(self::ENVIRONMENTS)) {
            throw new RuntimeException(
                'E2ESeeder only runs in '.implode(', ', self::ENVIRONMENTS)
                .' (APP_ENV='.app()->environment().'). It seeds accounts with a known password.'
            );
        }

        $this->seedSiteSettings();
        $this->call(RolesAndPermissionsSeeder::class);
        $this->seedUsers();
        $this->call(TriageSeeder::class);
        $this->seedDirectory();
        $this->seedForum();
        $this->seedReviews();
        $this->seedReportableReviews();
        $this->seedDoctorClaimProfiles();
        $this->seedVerification();
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
            self::ADMIN_TOTP_SECRET => $this->user(self::ADMIN_EMAIL, 'E2E Администратор', RoleCatalog::ADMINISTRATOR, UserKind::Staff),
            self::STAFF_MODERATOR_TOTP_SECRET => $this->user(self::STAFF_MODERATOR_EMAIL, 'E2E Модератор', RoleCatalog::MODERATOR, UserKind::Staff),
        ];

        // Only once the schema has app authentication. Set through the model so
        // the attribute's encrypted cast applies.
        if (Schema::hasColumn('users', 'app_authentication_secret')) {
            foreach ($staff as $secret => $user) {
                $user->forceFill(['app_authentication_secret' => $secret])->save();
            }
        }
        $this->user(self::COMMUNITY_MODERATOR_EMAIL, 'E2E Форум модератор', RoleCatalog::MEMBER, UserKind::Client);
        $this->user(self::MEMBER_EMAIL, 'E2E Член', RoleCatalog::MEMBER, UserKind::Client);

        foreach (self::MUTABLE_MEMBER_PREFIXES as $prefix) {
            for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
                $this->user("{$prefix}-{$attempt}@e2e.test", "E2E {$prefix} {$attempt}", RoleCatalog::MEMBER, UserKind::Client);
            }
        }
    }

    private function user(string $email, string $name, string $role, UserKind $kind): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                // W5-U: a fixed username, so seeded members can post at once.
                'username' => self::seededUsername($email),
                'password' => self::PASSWORD,
                'user_kind' => $kind,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([$role]);

        return $user;
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
                // List payloads carry these too: the card's „Јави се“ and open-now line.
                'office_hours' => ['Пон–Пет' => '08:00–16:00'],
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
        $communityModerator->syncRoles([RoleCatalog::MEMBER, RoleCatalog::FORUM_MODERATOR]);
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

    /**
     * One published facility review per attempt, written by "reported-{n}", for
     * the report spec to report, hide and see disappear (e2e/report.spec.ts).
     * Re-seeding puts it back up and clears its reports and votes.
     */
    private function seedReportableReviews(): void
    {
        $facility = Facility::query()->where('slug', self::FACILITY_SLUG)->firstOrFail();

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $author = User::query()->where('email', "reported-{$attempt}@e2e.test")->firstOrFail();

            $review = Review::query()->updateOrCreate(
                ['user_id' => $author->id, 'reviewable_type' => Facility::class, 'reviewable_id' => $facility->id],
                [
                    'rating' => 2,
                    'body' => "Рецензија за пријава {$attempt} (E2E).",
                    'status' => ReviewStatus::Approved,
                    'published_at' => now()->subDays(1 + $attempt),
                    'moderated_by_id' => null,
                    'moderated_at' => null,
                    'rejection_note' => null,
                    // W5-I: a re-seed also clears the removal placeholder.
                    'removed_at' => null,
                    'removal_category' => null,
                ],
            );

            $review->reports()->delete();
            DB::table('review_helpful_votes')->where('review_id', $review->id)->delete();
            $review->forceFill(['helpful_count' => 0])->save();
        }
    }

    /**
     * Re-seeding returns each profile to unmanaged, with no change requests
     * and its review without a reply.
     */
    private function seedDoctorClaimProfiles(): void
    {
        $author = User::query()->where('email', self::MEMBER_EMAIL)->firstOrFail();

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $doctor = Doctor::query()->updateOrCreate(
                ['slug' => self::DOCTOR_CLAIM_SLUG_PREFIX."-{$attempt}"],
                [
                    'full_name' => self::DOCTOR_CLAIM_NAME_PREFIX." {$attempt}",
                    'title' => 'д-р',
                    'bio' => 'Профил за E2E тестови на „Мој профил“.',
                    'city' => 'Скопје',
                    'phone' => "+389 70 100 00{$attempt}",
                    'accepts_new_patients' => true,
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );

            $doctor->forceFill(['owner_user_id' => null, 'owner_linked_at' => null, 'owner_linked_by_id' => null])->save();
            $doctor->changeRequests()->delete();

            $review = Review::query()->updateOrCreate(
                ['user_id' => $author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id],
                [
                    'rating' => 5,
                    'body' => "Внимателен и јасен лекар (E2E {$attempt}).",
                    'status' => ReviewStatus::Approved,
                    'published_at' => now()->subDays(2),
                ],
            );
            $review->removeResponse();
        }
    }

    /**
     * e2e/verification.spec.ts: the E2E doctor is „Верификуван“ (official
     * registers), the clinic stays „Неверификувана“.
     */
    private function seedVerification(): void
    {
        $writer = app(VerificationWriter::class);
        $writer->verify(Doctor::query()->where('slug', self::DOCTOR_SLUG)->firstOrFail(), VerificationBasis::OfficialRegisters, ['e2e']);
        $writer->unverify(Facility::query()->where('slug', self::FACILITY_SLUG)->firstOrFail(), 'e2e');
    }
}
