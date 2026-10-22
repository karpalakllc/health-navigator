<?php

namespace App\Support\Search;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Specialty;
use App\Support\MacedonianSearchVariants;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Typo-tolerant fallback for name searches that found nothing: „domenika“
 * finds „Доминика Славкоска“, „bozinovski“ the Божиновски, „kardolog“
 * Кардиологија.
 *
 * The query is compared in both scripts (MacedonianSearchVariants, as the
 * exact search does) against whole words of the stored name, by trigram
 * similarity — pg_trgm's strict_word_similarity on PostgreSQL, served by the
 * existing GIN trigram indexes (TrigramSearchIndexes); the same measure in
 * PHP (Trigram) on SQLite, over a LIKE-prefiltered, capped candidate list.
 *
 * Only rows at least THRESHOLD similar are kept, and of those only the ones
 * within WINDOW of the best: one close match does not drag in a tail of
 * merely similar names. Callers pass a builder with every other filter
 * (published, city, type, specialty…) already applied, so a fuzzy result
 * never shows a row the exact search would have hidden.
 *
 * Scoped: one instance per request, so the specialty scores are computed once.
 */
#[Scoped]
final class FuzzyNameSearch
{
    /** Shorter queries have too few trigrams to tell a typo from another name. */
    public const MIN_LENGTH = 4;

    public const THRESHOLD = 0.4;

    /** Specialties also match on word stems („kardiolog“), so need a little more. */
    public const SPECIALTY_THRESHOLD = 0.45;

    public const WINDOW = 0.1;

    /** Ranked rows per directory: more is a listing, not a correction. */
    public const MAX_MATCHES = 50;

    /** SQLite: candidates scored in PHP per directory. */
    private const SQLITE_CANDIDATES = 500;

    /** @var array<string, array<int, array{0: float, 1: string}>> */
    private array $specialtyCache = [];

    public static function eligible(?string $q): bool
    {
        if ($q === null) {
            return false;
        }

        $q = trim($q);

        return mb_strlen($q) >= self::MIN_LENGTH && preg_match('/\p{L}/u', $q) === 1;
    }

    /**
     * Doctors whose name is close to the term, plus the members of published
     * specialties whose name is.
     *
     * @param  Builder<Doctor>  $base
     */
    public function doctors(Builder $base, string $term): ?FuzzyMatch
    {
        $names = $this->rank($base, 'doctors.id', 'doctors.full_name', $term);
        $specialties = $this->specialties($term);

        if ($names === null && $specialties === []) {
            return null;
        }

        $specialtyBest = $specialties === [] ? 0.0 : reset($specialties)[0];
        $useSpecialty = $specialties !== [] && ($names === null || $specialtyBest > $names->bestScore);

        return new FuzzyMatch(
            scores: $names->scores ?? [],
            bestScore: max($names->bestScore ?? 0.0, $specialtyBest),
            suggestion: $useSpecialty ? reset($specialties)[1] : $names?->suggestion,
            specialtyIds: array_keys($specialties),
            specialtyScore: $specialtyBest,
        );
    }

    /**
     * Facilities (clinical or pharmacies — the caller's builder decides)
     * whose name is close to the term.
     *
     * @param  Builder<Facility>  $base
     */
    public function facilities(Builder $base, string $term): ?FuzzyMatch
    {
        return $this->rank($base, 'facilities.id', 'facilities.name', $term);
    }

    /**
     * Published specialties close to the term, best first: id => [similarity,
     * the matched words of the name]. A few dozen rows, scored in PHP.
     *
     * @return array<int, array{0: float, 1: string}>
     */
    public function specialties(string $term): array
    {
        $term = trim($term);

        if (! self::eligible($term)) {
            return [];
        }

        if (isset($this->specialtyCache[$term])) {
            return $this->specialtyCache[$term];
        }

        $variants = self::variants($term);
        $scores = [];

        foreach (Specialty::query()->published()->get(['id', 'name']) as $specialty) {
            $best = [0.0, ''];

            foreach ($variants as $variant) {
                foreach ([Trigram::bestExtent($variant, $specialty->name), Trigram::bestPrefix($variant, $specialty->name)] as $candidate) {
                    if ($candidate[0] > $best[0]) {
                        $best = $candidate;
                    }
                }
            }

            if ($best[0] >= self::SPECIALTY_THRESHOLD) {
                $scores[(int) $specialty->id] = $best;
            }
        }

        uasort($scores, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return $this->specialtyCache[$term] = self::withinWindow($scores);
    }

    /**
     * Of names some other engine found (Meilisearch's typo tolerance), the
     * words closest to the term — null when one of them contains the term
     * outright (an exact hit) or none is at least THRESHOLD similar.
     *
     * @param  list<string>  $names
     */
    public static function closestName(string $term, array $names): ?string
    {
        $match = self::closestNameMatch($term, $names);

        return $match !== false && $match[0] >= self::THRESHOLD && $match[1] !== '' ? $match[1] : null;
    }

    /**
     * closestName() with its similarity, below THRESHOLD too ([0.0, ''] when
     * nothing is alike); false when a name contains the term outright.
     *
     * @param  list<string>  $names
     * @return array{0: float, 1: string}|false
     */
    public static function closestNameMatch(string $term, array $names): array|false
    {
        $term = trim($term);

        if (! self::eligible($term)) {
            return [0.0, ''];
        }

        $variants = self::variants($term);
        $best = [0.0, ''];

        foreach ($names as $name) {
            $lower = mb_strtolower($name);
            $latin = MacedonianSearchVariants::cyrillicToLatin($name);

            foreach ($variants as $variant) {
                if (str_contains($lower, $variant) || str_contains($latin, $variant)) {
                    return false;
                }

                $candidate = Trigram::bestExtent($variant, $name);

                if ($candidate[0] > $best[0]) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * Restricts the doctor query to the match, best first. Add the usual
     * orderings after it: they break ties between equally close names.
     *
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public static function applyToDoctors(Builder $query, FuzzyMatch $match): Builder
    {
        $ids = array_keys($match->scores);

        $query->where(function (Builder $inner) use ($ids, $match): void {
            if ($ids !== []) {
                $inner->whereIn('doctors.id', $ids);
            }

            if ($match->specialtyIds !== []) {
                $inner->orWhereIn(
                    'doctors.id',
                    DB::table('doctor_specialty')->select('doctor_id')->whereIn('specialty_id', $match->specialtyIds),
                );
            }
        });

        return self::orderByRank($query, 'doctors.id', $ids);
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public static function applyToFacilities(Builder $query, FuzzyMatch $match): Builder
    {
        $ids = array_keys($match->scores);

        return self::orderByRank($query->whereIn('facilities.id', $ids), 'facilities.id', $ids);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>  $ids  best first
     * @return Builder<TModel>
     */
    private static function orderByRank(Builder $query, string $idColumn, array $ids): Builder
    {
        if ($ids === []) {
            return $query;
        }

        $wrapped = $query->getQuery()->getGrammar()->wrap($idColumn);
        $cases = '';

        foreach ($ids as $position => $id) {
            $cases .= ' WHEN '.(int) $id.' THEN '.$position;
        }

        return $query->orderByRaw("CASE {$wrapped}{$cases} ELSE ".count($ids).' END');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $base
     */
    private function rank(Builder $base, string $idColumn, string $nameColumn, string $term): ?FuzzyMatch
    {
        $term = trim($term);

        if (! self::eligible($term)) {
            return null;
        }

        $variants = self::variants($term);

        $rows = $base->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? $this->rankPostgres($base, $idColumn, $nameColumn, $variants)
            : $this->rankInPhp($base, $idColumn, $nameColumn, $variants);

        if ($rows === []) {
            return null;
        }

        $rows = self::withinWindow($rows);
        $best = reset($rows);

        return new FuzzyMatch(
            scores: array_map(static fn (array $row): float => $row[0], $rows),
            bestScore: $best[0],
            suggestion: self::suggestion($variants, $best[1]),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $base
     * @param  list<string>  $variants
     * @return array<int, array{0: float, 1: string}> id => [similarity, name], best first
     */
    private function rankPostgres(Builder $base, string $idColumn, string $nameColumn, array $variants): array
    {
        $wrapped = $base->getQuery()->getGrammar()->wrap($nameColumn);
        $score = 'GREATEST('.implode(', ', array_fill(0, count($variants), "strict_word_similarity(?, {$wrapped})")).')';

        $query = $base->clone()
            ->reorder()
            ->select([$idColumn.' as fuzzy_id', $nameColumn.' as fuzzy_name'])
            ->selectRaw("{$score} as fuzzy_score", $variants)
            // The operator form is what the GIN trigram index serves; it
            // compares against pg_trgm.strict_word_similarity_threshold.
            ->where(function (Builder $inner) use ($variants, $wrapped): void {
                foreach ($variants as $variant) {
                    $inner->orWhereRaw("? <<% {$wrapped}", [$variant]);
                }
            })
            ->orderByDesc('fuzzy_score')
            ->orderBy($idColumn)
            ->limit(self::MAX_MATCHES)
            ->toBase();

        try {
            // SET LOCAL, so the threshold never outlives this query — also
            // behind a transaction-pooling proxy.
            $connection = $base->getModel()->getConnection();
            $results = $connection->transaction(function () use ($connection, $query) {
                $connection->select("select set_config('pg_trgm.strict_word_similarity_threshold', ?, true)", [(string) self::THRESHOLD]);

                return $query->get();
            });
        } catch (QueryException $exception) {
            // pg_trgm missing (platform:preflight warns about it): no
            // suggestions, but the search itself still answers.
            Log::warning('Fuzzy name search unavailable: '.$exception->getMessage());

            return [];
        }

        $rows = [];

        foreach ($results as $row) {
            $rows[(int) $row->fuzzy_id] = [(float) $row->fuzzy_score, (string) $row->fuzzy_name];
        }

        return $rows;
    }

    /**
     * SQLite (tests, local tooling): candidates sharing at least one trigram
     * with the query, capped, scored with the same measure in PHP.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $base
     * @param  list<string>  $variants
     * @return array<int, array{0: float, 1: string}>
     */
    private function rankInPhp(Builder $base, string $idColumn, string $nameColumn, array $variants): array
    {
        $patterns = [];

        foreach ($variants as $variant) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $variant, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                for ($i = 0; $i + 3 <= mb_strlen($word); $i++) {
                    $trigram = mb_substr($word, $i, 3);

                    // SQLite's LIKE folds ASCII case only.
                    foreach ([$trigram, mb_convert_case($trigram, MB_CASE_TITLE), mb_strtoupper($trigram)] as $form) {
                        $patterns['%'.addcslashes($form, '%_\\').'%'] = true;
                    }
                }
            }
        }

        if ($patterns === []) {
            return [];
        }

        $wrapped = $base->getQuery()->getGrammar()->wrap($nameColumn);

        $candidates = $base->clone()
            ->reorder()
            ->select([$idColumn.' as fuzzy_id', $nameColumn.' as fuzzy_name'])
            ->where(function (Builder $inner) use ($patterns, $wrapped): void {
                foreach (array_keys($patterns) as $pattern) {
                    $inner->orWhereRaw("{$wrapped} LIKE ?", [$pattern]);
                }
            })
            ->orderBy($idColumn)
            ->limit(self::SQLITE_CANDIDATES)
            ->toBase()
            ->get();

        $rows = [];

        foreach ($candidates as $candidate) {
            $name = (string) $candidate->fuzzy_name;
            $score = max(array_map(static fn (string $variant): float => Trigram::bestExtent($variant, $name)[0], $variants));

            if ($score >= self::THRESHOLD) {
                $rows[(int) $candidate->fuzzy_id] = [$score, $name];
            }
        }

        // Best first; equal scores in id order, as the PostgreSQL query.
        uksort($rows, static fn (int $a, int $b): int => [$rows[$b][0], $a] <=> [$rows[$a][0], $b]);

        return array_slice($rows, 0, self::MAX_MATCHES, true);
    }

    /**
     * @param  array<int, array{0: float, 1: string}>  $rows  best first
     * @return array<int, array{0: float, 1: string}>
     */
    private static function withinWindow(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $cutoff = reset($rows)[0] - self::WINDOW;

        return array_filter($rows, static fn (array $row): bool => $row[0] >= $cutoff - 1e-9);
    }

    /**
     * The words of the best name that matched — „Доминика“ for „domenika“ —
     * so following the suggestion runs an exact search that finds it.
     *
     * @param  list<string>  $variants
     */
    private static function suggestion(array $variants, string $name): ?string
    {
        $best = [0.0, ''];

        foreach ($variants as $variant) {
            $candidate = Trigram::bestExtent($variant, $name);

            if ($candidate[0] > $best[0]) {
                $best = $candidate;
            }
        }

        return $best[1] !== '' ? $best[1] : null;
    }

    /**
     * Lower-cased Latin and Cyrillic forms of the term.
     *
     * @return list<string>
     */
    private static function variants(string $term): array
    {
        return array_values(array_unique(array_map(
            static fn (string $variant): string => mb_strtolower($variant),
            MacedonianSearchVariants::variants($term),
        )));
    }
}
