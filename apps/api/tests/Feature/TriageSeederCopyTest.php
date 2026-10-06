<?php

namespace Tests\Feature;

use App\Models\TriageFlow;
use App\Models\TriageOutcome;
use Database\Seeders\TriageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded guidance flow is what a Macedonian-only visitor reads at an
 * emergency; it used to be entirely English.
 */
class TriageSeederCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_string_of_the_seeded_flow_is_macedonian(): void
    {
        $this->seed(TriageSeeder::class);

        $flow = $this->getJson('/api/v1/triage/flow')->assertOk()->json('data');

        $strings = [$flow['title'], $flow['intro_body']];
        foreach ($flow['red_flags'] as $flag) {
            $strings[] = $flag['label'];
        }
        foreach ($flow['steps'] as $step) {
            $strings[] = $step['label'];
            foreach ($step['options'] as $option) {
                $strings[] = $option['label'];
            }
        }
        foreach (TriageOutcome::query()->get() as $outcome) {
            $strings[] = $outcome->title;
            $strings[] = $outcome->body;
            foreach ($outcome->handoffs as $handoff) {
                $strings[] = $handoff['label'];
            }
        }

        $this->assertCount(37, $strings);
        foreach ($strings as $string) {
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $string, "English left in: {$string}");
            // Russian/Bulgarian letters Macedonian does not have.
            $this->assertDoesNotMatchRegularExpression('/[йщъыьэюяёЙЩЪЫЬЭЮЯЁ]/u', $string, "Non-Macedonian letter in: {$string}");
        }
    }

    public function test_reseeding_translates_the_english_flow_in_place(): void
    {
        $legacy = TriageFlow::query()->create([
            'title' => 'General symptom guidance',
            'intro_body' => 'Answer a few general questions.',
            'is_published' => true,
        ]);

        $this->seed(TriageSeeder::class);
        $this->seed(TriageSeeder::class);

        // Not a second published flow next to the English one.
        $this->assertSame(1, TriageFlow::query()->count());
        $this->assertSame(TriageSeeder::TITLE, $legacy->refresh()->title);
    }
}
