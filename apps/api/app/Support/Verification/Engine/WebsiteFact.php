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
     * @param  bool  $onPage  the latest import of the institution's site listed the entry
     * @param  bool  $recent  a site import listed it within import.verification.website_max_age_days
     */
    public function __construct(
        public int $recordId,
        public int $facilityId,
        public bool $linked,
        public bool $highConfidence,
        public array $flags,
        public bool $trusted,
        public ?array $specialtyIds,
        public bool $onPage = true,
        public bool $recent = true,
    ) {}

    /** Counts as evidence: linked, certain, current, and on a site nobody flagged (or staff trust). */
    public function counts(): bool
    {
        return $this->linked && $this->highConfidence && $this->current() && ($this->flags === [] || $this->trusted);
    }

    /** Still on the page at the latest import of the site, and not too old. */
    public function current(): bool
    {
        return $this->onPage && $this->recent;
    }

    public function isFlagged(): bool
    {
        return $this->flags !== [] && ! $this->trusted;
    }

    public function trusting(): self
    {
        return new self($this->recordId, $this->facilityId, $this->linked, $this->highConfidence, $this->flags, true, $this->specialtyIds, $this->onPage, $this->recent);
    }
}
