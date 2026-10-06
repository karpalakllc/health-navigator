<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Support\TrigramSearchIndexes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The indexes docs/performance.md measured. Losing one does not fail any
 * behavioural test — it only makes a listing slow at volume — so assert them.
 */
class DatabaseIndexesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<list<string>>
     */
    private function indexedColumns(string $table): array
    {
        return array_map(fn (array $index) => $index['columns'], Schema::getIndexes($table));
    }

    public function test_lookup_indexes_exist_and_redundant_ones_are_gone(): void
    {
        $this->assertContains(['product_id', 'facility_id'], $this->indexedColumns('pharmacy_product'));
        $this->assertContains(['facility_id'], $this->indexedColumns('doctor_facility'));
        $this->assertContains(['department_id'], $this->indexedColumns('department_facility'));
        $this->assertContains(['user_id'], $this->indexedColumns('analytics_events'));
        $this->assertContains(['rating_avg', 'reviews_count'], $this->indexedColumns('doctors'));

        $reviews = $this->indexedColumns('reviews');
        $this->assertContains(['reviewable_type', 'reviewable_id', 'status', 'published_at'], $reviews);
        $this->assertNotContains(['reviewable_type', 'reviewable_id'], $reviews);
        $this->assertNotContains(['reviewable_type', 'reviewable_id', 'status'], $reviews);

        $posts = $this->indexedColumns('forum_posts');
        $this->assertContains(['forum_topic_id', 'status', 'published_at'], $posts);
        $this->assertNotContains(['forum_topic_id', 'status', 'created_at'], $posts);
    }

    public function test_trigram_indexes_serve_the_search_scopes_on_postgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_trgm is PostgreSQL only.');
        }

        $this->assertTrue(TrigramSearchIndexes::extensionInstalled());

        $existing = DB::table('pg_indexes')->where('schemaname', 'public')->pluck('indexname')->all();

        foreach (array_keys(TrigramSearchIndexes::COLUMNS) as $index) {
            $this->assertContains($index, $existing);
        }

        // The SQL the scope really emits (Latin + Cyrillic variants, ::text
        // casts), with sequential scans priced out so an empty test table
        // still shows whether the index is usable at all. Without published():
        // on an empty table the planner would rather bitmap-scan that btree.
        $query = Doctor::query()->searchName('petrovski');

        DB::statement('SET LOCAL enable_seqscan = off');
        $plan = implode("\n", array_column(
            array_map(fn ($row) => (array) $row, DB::select('EXPLAIN '.$query->toSql(), $query->getBindings())),
            'QUERY PLAN',
        ));

        $this->assertStringContainsString('doctors_full_name_trgm_index', $plan);
    }
}
