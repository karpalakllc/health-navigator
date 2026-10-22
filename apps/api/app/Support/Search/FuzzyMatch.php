<?php

namespace App\Support\Search;

/**
 * What a fuzzy fallback found for one directory: the rows whose name is close
 * to the query (id => similarity, best first), optionally the ids of
 * specialties close to it (their doctors are matches too), and the best
 * matching words as the „Дали мислевте“ suggestion.
 */
final readonly class FuzzyMatch
{
    /**
     * @param  array<int, float>  $scores  row id => similarity, best first
     * @param  list<int>  $specialtyIds
     */
    public function __construct(
        public array $scores,
        public float $bestScore,
        public ?string $suggestion,
        public array $specialtyIds = [],
        public float $specialtyScore = 0.0,
    ) {}

    /**
     * Only what is at least $cutoff similar — null when nothing is. Unified
     * search cuts every directory at the overall best match minus the
     * window: „acibadem“ matches the hospital, not doctors named „Адем“.
     */
    public function above(float $cutoff): ?self
    {
        $scores = array_filter($this->scores, static fn (float $score): bool => $score >= $cutoff - 1e-9);
        $specialtyIds = $this->specialtyScore >= $cutoff - 1e-9 ? $this->specialtyIds : [];

        if ($scores === [] && $specialtyIds === []) {
            return null;
        }

        return new self($scores, $this->bestScore, $this->suggestion, $specialtyIds, $specialtyIds === [] ? 0.0 : $this->specialtyScore);
    }

    public function isEmpty(): bool
    {
        return $this->scores === [] && $this->specialtyIds === [];
    }

    /**
     * The better of two matches' suggestions (by similarity).
     */
    public static function bestSuggestion(?self ...$matches): ?string
    {
        $best = null;

        foreach ($matches as $match) {
            if ($match?->suggestion !== null && ($best === null || $match->bestScore > $best->bestScore)) {
                $best = $match;
            }
        }

        return $best?->suggestion;
    }
}
