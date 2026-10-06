<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use App\Support\DisplayName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Owner decision 2026-10-06: reviews and forum posts show a public display
 * name the member chose, never `users.name` (their real, private name).
 */
class DisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private const REAL_NAME = 'Марија Костовска';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => self::REAL_NAME,
            'display_name' => 'Марија К.',
            'email' => 'marija@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ], $overrides);
    }

    private function member(): User
    {
        return User::factory()->create([
            'name' => self::REAL_NAME,
            'display_name' => 'Марија К.',
        ]);
    }

    // ---- registration ----

    public function test_registration_requires_a_display_name(): void
    {
        $payload = $this->registration();
        unset($payload['display_name']);

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('display_name');
    }

    public function test_registration_stores_the_trimmed_display_name(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registration([
            'display_name' => "  Марија\t  К.  ",
        ]))->assertStatus(202);

        $user = User::query()->where('email', 'marija@example.com')->sole();

        $this->assertSame('Марија К.', $user->display_name);
        $this->assertSame(self::REAL_NAME, $user->name);
    }

    public function test_display_names_are_limited_to_letters_spaces_and_name_punctuation(): void
    {
        foreach (['Марија2', '<b>Марија</b>', '.Марија', '@marija', 'ана_м', str_repeat('а', 41), '   '] as $invalid) {
            $this->forgetRateLimits();
            $this->postJson('/api/v1/auth/register', $this->registration(['display_name' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('display_name');
        }

        foreach (["O'Neil", 'Ана-Марија', 'Јован Ѓ.', 'Zoë M.'] as $valid) {
            $this->assertMatchesRegularExpression(DisplayName::PATTERN, $valid);
        }
    }

    /**
     * Names that would let a member pass as staff, a clinician or the
     * platform itself next to a review or a forum answer.
     *
     * @return array<string, array{string, string}>
     */
    public static function impersonatingNames(): array
    {
        return [
            'Cyrillic doctor title' => ['Д-р Марко', 'reserved'],
            'title with a dot and no space' => ['Д-р.Марко', 'reserved'],
            'Latin doctor title' => ['Dr. Marko', 'reserved'],
            'upper-case title' => ['DR Marko', 'reserved'],
            'title without punctuation' => ['Др Марко', 'reserved'],
            'doctor spelled out' => ['Доктор Ана', 'reserved'],
            'doctor in Latin' => ['Doktor Ana', 'reserved'],
            'professor' => ['Проф. Ана', 'reserved'],
            'Latin professor' => ['Prof Ana', 'reserved'],
            'administrator' => ['Администратор', 'reserved'],
            'admin in Latin' => ['Admin', 'reserved'],
            'admin as a prefix' => ['Administrator Ana', 'reserved'],
            'admin transliterated' => ['Админ', 'reserved'],
            'moderator' => ['Модератор Тим', 'reserved'],
            'Latin moderator' => ['moderator', 'reserved'],
            'team' => ['Zdravje Team', 'reserved'],
            'team alone in Cyrillic' => ['Тим за поддршка', 'reserved'],
            'support' => ['Support', 'reserved'],
            'platform name in Cyrillic' => ['Здравје', 'reserved'],
            'platform name in Latin' => ['zdravje', 'reserved'],
            'official' => ['Official Ana', 'reserved'],
            'official in Macedonian' => ['Официјален профил', 'reserved'],
            'hyphenated role' => ['Ана-Админ', 'reserved'],
            'mixed-script admin' => ['Аdmin', 'mixed_script'],
            'mixed-script name' => ['Mарија', 'mixed_script'],
            'single letter' => ['x', 'too_short'],
            'single letter and a dot' => ['Ј.', 'too_short'],
        ];
    }

    #[DataProvider('impersonatingNames')]
    public function test_display_names_cannot_claim_a_title_role_or_the_platform(string $name, string $reason): void
    {
        $this->assertSame($reason, DisplayName::rejection(DisplayName::normalize($name)));

        $this->forgetRateLimits();
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/auth/register', $this->registration(['display_name' => $name]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name' => __("validation.custom.display_name.{$reason}", [], 'mk')]);
    }

    /**
     * Characters that render like a reserved term but are not its letters:
     * an invisible combining mark, full-width and mathematical letters, a
     * Greek capital Alpha.
     *
     * @return array<string, array{string}>
     */
    public static function disguisedNames(): array
    {
        return [
            'combining grapheme joiner in Latin' => ["Ad\u{034F}min"],
            'combining grapheme joiner in Cyrillic' => ["Д\u{034F}-р Марко"],
            'variation selector' => ["Admin\u{FE0F}"],
            'full-width letters' => ['Ａｄｍｉｎ'],
            'mathematical bold letters' => ['𝐀𝐝𝐦𝐢𝐧'],
            'Greek capital alpha' => ['Αdmin'],
        ];
    }

    #[DataProvider('disguisedNames')]
    public function test_display_names_cannot_disguise_a_reserved_term(string $name): void
    {
        $this->forgetRateLimits();
        $this->postJson('/api/v1/auth/register', $this->registration(['display_name' => $name]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('display_name');
    }

    public function test_ordinary_names_that_resemble_reserved_terms_are_allowed(): void
    {
        foreach (['Драган', 'Dragan P.', 'Тимчо', 'Profirovski', 'Здравко', 'Админа'] as $name) {
            if ($name === 'Админа') {
                // "админ" is matched as a prefix on purpose.
                $this->assertSame('reserved', DisplayName::rejection($name));

                continue;
            }

            $this->assertNull(DisplayName::rejection($name), $name);
        }

        foreach (["O'Neil", 'Ана-Марија', 'Јован Ѓ.', 'Zoë M.', 'Марија К.', 'Ана Dimitrova', 'Ли'] as $name) {
            $this->assertNull(DisplayName::rejection($name), $name);
        }
    }

    public function test_reserved_name_errors_are_localised(): void
    {
        $this->forgetRateLimits();
        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/register', $this->registration(['display_name' => 'Dr Marko']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name' => 'cannot contain a title or role']);

        $this->forgetRateLimits();
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/auth/register', $this->registration(['display_name' => 'x']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name' => 'барем две букви']);
    }

    public function test_display_names_do_not_have_to_be_unique(): void
    {
        $this->member();

        $this->forgetRateLimits();
        Notification::fake();
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'other@example.com']))
            ->assertStatus(202);

        $this->assertSame(2, User::query()->where('display_name', 'Марија К.')->count());
    }

    // ---- account page ----

    public function test_a_member_can_change_their_display_name(): void
    {
        $member = $this->member();
        $token = $member->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/profile', ['display_name' => ' Мара  К. '])
            ->assertOk()
            ->assertJsonPath('data.user.display_name', 'Мара К.')
            ->assertJsonPath('data.user.name', self::REAL_NAME);

        $this->assertSame('Мара К.', $member->fresh()->display_name);
    }

    public function test_the_profile_update_validates_and_requires_a_session(): void
    {
        $this->patchJson('/api/v1/me/profile', ['display_name' => 'Мара'])->assertUnauthorized();

        $member = $this->member();
        $token = $member->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/profile', ['display_name' => 'Мара 123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('display_name');

        $this->assertSame('Марија К.', $member->fresh()->display_name);
    }

    public function test_the_profile_update_rejects_reserved_names(): void
    {
        $this->forgetRateLimits();
        $member = $this->member();

        $this->actingAs($member)
            ->patchJson('/api/v1/me/profile', ['display_name' => 'Модератор Тим'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('display_name');

        $this->assertSame('Марија К.', $member->fresh()->display_name);
    }

    public function test_profile_updates_are_throttled_per_member(): void
    {
        $this->forgetRateLimits();
        $member = $this->member();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($member)
                ->patchJson('/api/v1/me/profile', ['display_name' => 'Мара '.mb_chr(0x0410 + $i).'.'])
                ->assertOk();
        }

        $this->actingAs($member)
            ->patchJson('/api/v1/me/profile', ['display_name' => 'Мара Б.'])
            ->assertStatus(429);

        // Another member is unaffected.
        $this->actingAs(User::factory()->create())
            ->patchJson('/api/v1/me/profile', ['display_name' => 'Јана'])
            ->assertOk();
    }

    // ---- public surfaces never carry the real name ----

    public function test_public_reviews_show_the_display_name(): void
    {
        $member = $this->member();
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);

        Review::factory()->approved()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);

        $response = $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.author_name', 'Марија К.');

        $this->assertStringNotContainsString('Костовска', $response->getContent());
    }

    public function test_forum_lists_topics_and_posts_show_the_display_name(): void
    {
        $member = $this->member();
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'user_id' => $member->id,
            'slug' => 'help-topic',
            'title' => 'Hydration question',
        ]);
        ForumPost::factory()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $member->id,
        ]);

        $responses = [
            $this->getJson('/api/v1/forum/categories/general/topics')
                ->assertOk()
                ->assertJsonPath('data.0.author_name', 'Марија К.'),
            $this->getJson('/api/v1/forum/topics?q=hydration')
                ->assertOk()
                ->assertJsonPath('data.0.author_name', 'Марија К.'),
            $this->getJson('/api/v1/forum/categories/general/topics/help-topic')
                ->assertOk()
                ->assertJsonPath('data.topic.author_name', 'Марија К.')
                ->assertJsonPath('data.topic.author.name', 'Марија К.')
                ->assertJsonPath('data.posts.0.author_name', 'Марија К.')
                ->assertJsonPath('data.posts.0.author.name', 'Марија К.'),
            $this->getJson('/api/v1/forum/topics/recent')->assertOk(),
        ];

        foreach ($responses as $response) {
            $this->assertStringNotContainsString('Костовска', $response->getContent());
        }
    }

    public function test_accounts_created_without_one_get_a_short_default_not_the_full_name(): void
    {
        $user = User::factory()->create(['name' => 'Петар Николовски', 'display_name' => null]);

        $this->assertSame('Петар Н.', $user->display_name);
        $this->assertSame('Петар Н.', $user->publicName());
    }

    // ---- backfill ----

    public function test_the_migration_backfills_first_name_and_last_initial(): void
    {
        $files = glob(database_path('migrations/*_add_display_name_to_users.php')) ?: [];
        $this->assertCount(1, $files);
        $migration = require $files[0];

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'display_name'));

        $ids = [];
        foreach (['Марија Костовска', 'Ана Марија Петровска', 'Бојан', '  ана   стојанова '] as $i => $name) {
            $ids[$name] = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => "legacy{$i}@example.com",
                'password' => 'x',
                'user_kind' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration->up();

        $this->assertSame([
            'Марија Костовска' => 'Марија К.',
            'Ана Марија Петровска' => 'Ана П.',
            'Бојан' => 'Бојан',
            '  ана   стојанова ' => 'ана С.',
        ], array_map(
            fn (int $id): ?string => DB::table('users')->where('id', $id)->value('display_name'),
            $ids,
        ));
    }
}
