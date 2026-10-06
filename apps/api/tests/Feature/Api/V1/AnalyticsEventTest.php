<?php

namespace Tests\Feature\Api\V1;

use App\Models\AnalyticsEvent;
use App\Models\Doctor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_review_submission_records_analytics_event(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'dr-test']);
        $member = User::factory()->create();

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/doctors/dr-test/reviews', [
            'rating' => 4,
            'body' => 'Helpful consultation with clear follow-up advice.',
        ])->assertCreated();

        $this->assertDatabaseHas('analytics_events', [
            'event' => 'review.submitted',
            'user_id' => $member->id,
        ]);
    }

    public function test_search_is_counted_as_an_aggregate_not_an_event(): void
    {
        $this->getJson('/api/v1/search?q=cardio')
            ->assertOk();

        $this->assertDatabaseMissing('analytics_events', [
            'event' => 'search.query',
        ]);
        $this->assertDatabaseHas('search_term_daily', [
            'term' => 'cardio',
            'count' => 1,
        ]);
    }
}
