<?php

use App\Support\SearchTermNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Search analytics become anonymous daily aggregates.
 *
 * `search.query` events used to keep the raw query next to a user id for 180
 * days. Existing events are folded into the aggregate table here and then
 * deleted, so no search text stays linked to an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_term_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('term', SearchTermNormalizer::MAX_LENGTH);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['date', 'term']);
            $table->index('date');
        });

        /** @var array<string, array{date: string, term: string, count: int}> $buckets */
        $buckets = [];

        DB::table('analytics_events')
            ->where('event', 'search.query')
            ->select(['id', 'properties', 'occurred_at'])
            ->chunkById(2000, function ($rows) use (&$buckets): void {
                foreach ($rows as $row) {
                    $properties = is_string($row->properties) ? json_decode($row->properties, true) : null;
                    $raw = is_array($properties) && is_string($properties['q'] ?? null) ? $properties['q'] : null;
                    $term = SearchTermNormalizer::normalize($raw);

                    if ($term === null || $row->occurred_at === null) {
                        continue;
                    }

                    $date = Carbon::parse($row->occurred_at)->toDateString();
                    $key = $date."\0".$term;

                    $buckets[$key] ??= ['date' => $date, 'term' => $term, 'count' => 0];
                    $buckets[$key]['count']++;
                }
            });

        foreach (array_chunk(array_values($buckets), 500) as $chunk) {
            DB::table('search_term_daily')->insert($chunk);
        }

        DB::table('analytics_events')->where('event', 'search.query')->delete();
    }

    /**
     * The raw events are gone for good; rolling back only removes the aggregates.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_term_daily');
    }
};
