<?php

use App\Support\Usernames\TemporaryUsername;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameTermMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The look-alike form of a username (`username_skeleton`, Latin „papa“ =
 * Cyrillic „рара“) becomes unique in the database, as `username` and
 * `username_normalized` already are: the validator checked it, but two
 * concurrent sign-ups of look-alikes could both commit.
 *
 * Skeletons are recomputed first — they now also fold „rn“ to „m“ and „vv“ to
 * „w“ — for users, the username history and the list terms (whose own
 * skeleton does not fold letter pairs, UsernameNormalizer::termSkeleton()).
 * New list terms for spellings the folding does not cover are added unless
 * already there.
 *
 * Accounts that now share a skeleton are resolved deterministically: the
 * account that chose its name (not a temporary one) keeps it, then the
 * earliest-created, then the lowest id; the others get a temporary name and
 * are asked to choose again at the next sign-in. Deleted (anonymised)
 * accounts hold no username and are not involved.
 */
return new class extends Migration
{
    /** @var array<string, array{category: string, terms: list<string>}> */
    private const NEW_CONTAINS = [
        'brand' => ['category' => 'brand', 'terms' => ['zdravie', 'zdrawje']],
        'staff' => ['category' => 'staff', 'terms' => ['izbrisan']],
        'medical' => ['category' => 'medical', 'terms' => ['ljekar']],
    ];

    public function up(): void
    {
        DB::table('users')->whereNotNull('username')->select(['id', 'username'])->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'username_skeleton' => UsernameNormalizer::skeleton($user->username),
                ]);
            }
        });

        DB::table('username_history')->select(['id', 'username'])->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('username_history')->where('id', $row->id)->update([
                    'username_skeleton' => UsernameNormalizer::skeleton($row->username),
                ]);
            }
        });

        DB::table('username_terms')->select(['id', 'term_normalized'])->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('username_terms')->where('id', $row->id)->update([
                    'term_skeleton' => UsernameNormalizer::termSkeleton((string) $row->term_normalized),
                ]);
            }
        });

        $now = now();
        foreach (self::NEW_CONTAINS as $group) {
            foreach ($group['terms'] as $term) {
                DB::table('username_terms')->insertOrIgnore([
                    'kind' => 'reserved',
                    'term' => $term,
                    'term_normalized' => UsernameNormalizer::key($term),
                    'term_skeleton' => UsernameNormalizer::termSkeleton(UsernameNormalizer::key($term)),
                    'language' => 'any',
                    'match_type' => 'contains',
                    'category' => $group['category'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->resolveCollisions();

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['username_skeleton']);
            $table->unique('username_skeleton');
        });

        UsernameTermMatcher::forget();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username_skeleton']);
            $table->index('username_skeleton');
        });
    }

    private function resolveCollisions(): void
    {
        $shared = DB::table('users')
            ->whereNotNull('username_skeleton')
            ->groupBy('username_skeleton')
            ->havingRaw('count(*) > 1')
            ->pluck('username_skeleton');

        foreach ($shared as $skeleton) {
            $losers = DB::table('users')
                ->where('username_skeleton', $skeleton)
                ->orderBy('must_choose_username')
                ->orderByRaw('case when created_at is null then 1 else 0 end')
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            foreach ($losers as $id) {
                $temporary = TemporaryUsername::generate();

                DB::table('users')->where('id', $id)->update([
                    'username' => $temporary,
                    'username_normalized' => UsernameNormalizer::key($temporary),
                    'username_skeleton' => UsernameNormalizer::skeleton($temporary),
                    'must_choose_username' => true,
                ]);
            }
        }
    }
};
