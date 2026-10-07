<?php

namespace App\Support\Verification\Engine;

/**
 * One entry of an institution's own staff page (website import) pointing at
 * a doctor profile.
 */
final readonly class WebsiteFact
{
    /**
     * @param  bool  $linked  the profile is still linked to that institution
     * @param  bool  $highConfidence  the research marked the entry „high“ confidence
     * @param  list<string>  $flags  warnings about the site (SourceNotes: compromised, stale)
     * @param  bool  $trusted  staff decided to trust the flagged site („Trust this website“)
     * @param  list<int>|null  $specialtyIds  our specialties the page's wording maps to (null: none stated)
     */
    public function __construct(
        public int $recordId,
        public int $facilityId,
        public bool $linked,
        public bool $highConfidence,
        public array $flags,
        public bool $trusted,
        public ?array $specialtyIds,
    ) {}

    /** Counts as evidence: linked, certain, and on a site nobody flagged (or staff trust). */
    public function counts(): bool
    {
        return $this->linked && $this->highConfidence && ($this->flags === [] || $this->trusted);
    }

    public function isFlagged(): bool
    {
        return $this->flags !== [] && ! $this->trusted;
    }

    public function trusting(): self
    {
        return new self($this->recordId, $this->facilityId, $this->linked, $this->highConfidence, $this->flags, true, $this->specialtyIds);
    }
}
