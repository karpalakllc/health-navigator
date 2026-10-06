<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeHighlightsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/home/highlights';

    private function approvedReview(Doctor|Facility $target, array $overrides = []): Review
    {
        return Review::factory()->approved()->create([
            'reviewable_type' => $target::class,
            'reviewable_id' => $target->id,
            'body' => 'Многу љубезен пристап и јасно објаснување.',
            ...$overrides,
        ]);
    }

    public function test_specialties_are_ranked_by_published_doctor_count(): void
    {
        $cardio = Specialty::factory()->create(['slug' => 'kardiologija', 'name' => 'Кардиологија', 'is_published' => true]);
        $pedi = Specialty::factory()->create(['slug' => 'pedijatrija', 'name' => 'Педијатрија', 'is_published' => true]);
        Specialty::factory()->create(['slug' => 'prazna', 'name' => 'Празна', 'is_published' => true]);
        $hidden = Specialty::factory()->unpublished()->create(['slug' => 'skriena']);

        foreach (Doctor::factory()->count(3)->create(['is_published' => true]) as $doctor) {
            $doctor->specialties()->attach($pedi->id, ['is_primary' => true]);
        }
        Doctor::factory()->create(['is_published' => true])->specialties()->attach($cardio->id, ['is_primary' => true]);
        // Unpublished and deleted doctors do not count.
        foreach (Doctor::factory()->unpublished()->count(5)->create() as $doctor) {
            $doctor->specialties()->attach($cardio->id, ['is_primary' => true]);
        }
        $deleted = Doctor::factory()->create(['is_published' => true]);
        $deleted->specialties()->attach($cardio->id, ['is_primary' => true]);
        $deleted->delete();
        Doctor::factory()->create(['is_published' => true])->specialties()->attach($hidden->id, ['is_primary' => true]);

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(2, 'data.specialties')
            ->assertJsonPath('data.specialties.0', ['slug' => 'pedijatrija', 'name' => 'Педијатрија', 'doctors_count' => 3])
            ->assertJsonPath('data.specialties.1', ['slug' => 'kardiologija', 'name' => 'Кардиологија', 'doctors_count' => 1]);
    }

    public function test_specialties_are_capped(): void
    {
        foreach (range(1, 10) as $i) {
            $specialty = Specialty::factory()->create(['slug' => "s-{$i}", 'is_published' => true]);
            Doctor::factory()->create(['is_published' => true])->specialties()->attach($specialty->id, ['is_primary' => true]);
        }

        $this->getJson(self::URI)->assertOk()->assertJsonCount(8, 'data.specialties');
    }

    public function test_cities_count_published_doctors_and_merge_spellings(): void
    {
        Doctor::factory()->count(2)->create(['is_published' => true, 'city' => 'Скопје']);
        Doctor::factory()->create(['is_published' => true, 'city' => ' скопје ']);
        Doctor::factory()->count(2)->create(['is_published' => true, 'city' => 'Битола']);
        Doctor::factory()->create(['is_published' => true, 'city' => 'Охрид']);
        Doctor::factory()->unpublished()->count(4)->create(['city' => 'Охрид']);
        Doctor::factory()->create(['is_published' => true, 'city' => null]);
        Doctor::factory()->create(['is_published' => true, 'city' => '']);
        // Facilities do not count: the chip opens the doctor directory.
        Facility::factory()->count(3)->create(['is_published' => true, 'city' => 'Прилеп']);

        $this->getJson(self::URI)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'specialties' => [],
                    'cities' => [
                        ['name' => 'Скопје', 'doctors_count' => 3],
                        ['name' => 'Битола', 'doctors_count' => 2],
                        ['name' => 'Охрид', 'doctors_count' => 1],
                    ],
                    'recent_reviews' => [],
                ],
            ]);
    }

    public function test_recent_reviews_are_approved_newest_first_with_target_and_excerpt(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true, 'slug' => 'ana-petrovska', 'full_name' => 'д-р Ана Петровска']);
        $clinic = Facility::factory()->create(['is_published' => true, 'slug' => 'klinika-ana', 'name' => 'Клиника Ана', 'type' => FacilityType::Clinic]);
        $pharmacy = Facility::factory()->pharmacy()->create(['is_published' => true, 'slug' => 'apteka-ohrid', 'name' => 'Аптека Охрид']);

        $long = str_repeat('Зборови кои се повторуваат ', 20);
        $this->approvedReview($doctor, ['published_at' => now()->subDays(3), 'body' => $long, 'rating' => 5]);
        $this->approvedReview($clinic, ['published_at' => now()->subDays(2)]);
        $this->approvedReview($pharmacy, ['published_at' => now()->subDay()]);
        Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id, 'body' => 'Pending', 'status' => ReviewStatus::Pending]);
        Review::factory()->rejected()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id, 'body' => 'Rejected']);

        $response = $this->getJson(self::URI)->assertOk()->assertJsonCount(3, 'data.recent_reviews');

        $response
            ->assertJsonPath('data.recent_reviews.0.target', ['kind' => 'pharmacy', 'slug' => 'apteka-ohrid', 'name' => 'Аптека Охрид'])
            ->assertJsonPath('data.recent_reviews.1.target', ['kind' => 'facility', 'slug' => 'klinika-ana', 'name' => 'Клиника Ана'])
            ->assertJsonPath('data.recent_reviews.2.target', ['kind' => 'doctor', 'slug' => 'ana-petrovska', 'name' => 'д-р Ана Петровска'])
            ->assertJsonPath('data.recent_reviews.2.rating', 5);

        $excerpt = $response->json('data.recent_reviews.2.excerpt');
        $this->assertLessThanOrEqual(161, mb_strlen($excerpt));
        $this->assertStringEndsWith('…', $excerpt);
        $this->assertStringStartsWith('Зборови кои', $excerpt);
        $this->assertSame(
            ['id', 'rating', 'excerpt', 'author_name', 'published_at', 'target'],
            array_keys($response->json('data.recent_reviews.0')),
        );
    }

    public function test_recent_reviews_skip_unpublished_deleted_and_bodiless_targets_and_are_capped(): void
    {
        $visible = Doctor::factory()->create(['is_published' => true]);
        $unpublishedDoctor = Doctor::factory()->unpublished()->create();
        $deletedDoctor = Doctor::factory()->create(['is_published' => true]);
        $unpublishedClinic = Facility::factory()->unpublished()->create(['type' => FacilityType::Clinic]);

        $this->approvedReview($unpublishedDoctor, ['body' => 'Скриен лекар']);
        $this->approvedReview($deletedDoctor, ['body' => 'Избришан лекар']);
        $deletedDoctor->delete();
        $this->approvedReview($unpublishedClinic, ['body' => 'Скриена клиника']);
        $this->approvedReview($visible, ['body' => null]);
        $this->approvedReview($visible, ['body' => '']);

        foreach (range(1, 5) as $i) {
            $this->approvedReview($visible, ['body' => "Видлива {$i}", 'published_at' => now()->subMinutes(10 - $i)]);
        }

        $bodies = collect($this->getJson(self::URI)->assertOk()->json('data.recent_reviews'))->pluck('excerpt')->all();

        $this->assertSame(['Видлива 5', 'Видлива 4', 'Видлива 3', 'Видлива 2'], $bodies);
    }

    public function test_pharmacy_reviews_disappear_while_the_module_is_off(): void
    {
        $pharmacy = Facility::factory()->pharmacy()->create(['is_published' => true]);
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $this->approvedReview($pharmacy, ['body' => 'Аптека']);
        $this->approvedReview($doctor, ['body' => 'Лекар']);

        $this->getJson(self::URI)->assertJsonCount(2, 'data.recent_reviews');

        SiteSetting::current()->update(['public_pharmacies' => false]);

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(1, 'data.recent_reviews')
            ->assertJsonPath('data.recent_reviews.0.excerpt', 'Лекар');
    }

    public function test_authors_appear_by_public_display_name_only(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $named = User::factory()->create(['name' => 'Марија Костовска', 'email' => 'marija@example.test']);
        $named->forceFill(['display_name' => 'Марија од Битола'])->save();
        $unnamed = User::factory()->create(['name' => 'Петар Георгиев', 'email' => 'petar@example.test']);
        // A legacy account without a display name falls back to the suggested
        // short form, never the full name.
        DB::table('users')->where('id', $unnamed->id)->update(['display_name' => null]);

        $this->approvedReview($doctor, ['user_id' => $named->id, 'published_at' => now()->subMinute()]);
        $this->approvedReview($doctor, ['user_id' => $unnamed->id, 'published_at' => now()]);

        $response = $this->getJson(self::URI)->assertOk();

        $response
            ->assertJsonPath('data.recent_reviews.0.author_name', 'Петар Г.')
            ->assertJsonPath('data.recent_reviews.1.author_name', 'Марија од Битола');

        $content = (string) $response->getContent();
        foreach (['Марија Костовска', 'Петар Георгиев', 'marija@example.test', 'petar@example.test'] as $private) {
            $this->assertStringNotContainsString($private, $content);
        }
        $this->assertStringNotContainsString('user_id', $content);
    }

    public function test_is_publicly_cacheable_and_busted_when_a_review_is_moderated(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $review = Review::factory()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'body' => 'Чека модерација',
        ]);

        $first = $this->getJson(self::URI)->assertOk()->assertJsonCount(0, 'data.recent_reviews');
        $this->assertStringContainsString('public', (string) $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $first->headers->get('Cache-Control'));
        $this->getJson(self::URI, ['If-None-Match' => $first->headers->get('ETag')])->assertStatus(304);

        $review->approve(User::factory()->moderator()->create());

        $this->getJson(self::URI)->assertOk()->assertJsonPath('data.recent_reviews.0.excerpt', 'Чека модерација');

        $review->reject(User::factory()->moderator()->create());

        $this->getJson(self::URI)->assertOk()->assertJsonCount(0, 'data.recent_reviews');
    }

    public function test_is_served_from_cache_with_a_fixed_query_count(): void
    {
        $specialty = Specialty::factory()->create(['is_published' => true]);
        $users = User::factory()->count(4)->create();
        foreach (range(1, 4) as $i) {
            $doctor = Doctor::factory()->create(['is_published' => true, 'city' => "Град {$i}"]);
            $doctor->specialties()->attach($specialty->id, ['is_primary' => true]);
            $this->approvedReview($doctor, ['user_id' => $users[$i - 1]->id]);
            $this->approvedReview(Facility::factory()->create(['is_published' => true, 'type' => FacilityType::Clinic]), ['user_id' => $users[$i - 1]->id]);
        }
        SiteSetting::current();

        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $this->getJson(self::URI)->assertOk()->assertJsonCount(4, 'data.recent_reviews');
        // specialties, cities, reviews, users, doctors, facilities — no per-review queries.
        $this->assertLessThanOrEqual(8, $count);

        $count = 0;
        $this->getJson(self::URI)->assertOk();
        $this->assertSame(0, $count);
    }

    public function test_returns_empty_lists_on_an_empty_directory(): void
    {
        $this->getJson(self::URI)
            ->assertOk()
            ->assertExactJson(['data' => ['specialties' => [], 'cities' => [], 'recent_reviews' => []]]);
    }
}
