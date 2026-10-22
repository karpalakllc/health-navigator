<?php

namespace Tests\Unit\Support\Search;

use App\Support\Search\FuzzyNameSearch;
use App\Support\Search\Trigram;
use PHPUnit\Framework\TestCase;

class TrigramTest extends TestCase
{
    public function test_it_matches_pg_trgm_on_known_values(): void
    {
        // Values from PostgreSQL 16: select similarity(...), strict_word_similarity(...).
        $this->assertEqualsWithDelta(0.375, Trigram::similarity('кардолог', 'Кардиологија'), 1e-6);
        $this->assertEqualsWithDelta(0.5, Trigram::bestExtent('доменика', 'Доминика Славкоска')[0], 1e-6);
        $this->assertEqualsWithDelta(0.5714286, Trigram::bestExtent('бозиновски', 'Зоран Божиновски')[0], 1e-6);
        $this->assertEqualsWithDelta(0.7727272, Trigram::bestExtent('клиничка болница штип', 'ЈЗУ Клиничка болница Тетово')[0], 1e-6);
    }

    public function test_the_best_extent_is_returned_as_written(): void
    {
        $this->assertSame('Доминика', Trigram::bestExtent('доменика', 'Доминика Славкоска')[1]);
        $this->assertSame('Божиновска', Trigram::bestExtent('бозиновска', 'Весна Радуловиќ-Божиновска')[1]);
        $this->assertSame('Клиничка болница', Trigram::bestExtent('клиничка болница штип', 'ЈЗУ Клиничка болница Тетово')[1]);
        $this->assertSame([0.0, ''], Trigram::bestExtent('', 'Доминика'));
    }

    public function test_prefix_similarity_reads_a_stem_as_the_longer_word(): void
    {
        [$score, $word] = Trigram::bestPrefix('кардолог', 'Кардиологија');

        $this->assertGreaterThan(FuzzyNameSearch::SPECIALTY_THRESHOLD, $score);
        $this->assertSame('Кардиологија', $word);
    }

    public function test_closest_name_only_suggests_near_misses(): void
    {
        $this->assertSame('Доминика', FuzzyNameSearch::closestName('domenika', ['Доминика Славкоска']));
        // An exact hit (either script) is not a typo.
        $this->assertNull(FuzzyNameSearch::closestName('dominika', ['Доминика Славкоска']));
        $this->assertNull(FuzzyNameSearch::closestName('Доминика', ['Доминика Славкоска']));
        // A hit on another field (a city, a description) is not a typo either.
        $this->assertNull(FuzzyNameSearch::closestName('skopje', ['Доминика Славкоска']));
        $this->assertNull(FuzzyNameSearch::closestName('dom', ['Доминика Славкоска']));
    }

    public function test_eligibility(): void
    {
        $this->assertTrue(FuzzyNameSearch::eligible('domenika'));
        $this->assertFalse(FuzzyNameSearch::eligible('dom'));
        $this->assertFalse(FuzzyNameSearch::eligible('12345'));
        $this->assertFalse(FuzzyNameSearch::eligible(null));
    }
}
