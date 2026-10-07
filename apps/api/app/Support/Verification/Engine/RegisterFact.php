<?php

namespace App\Support\Verification\Engine;

/**
 * One ФЗОМ register record (an institution = tax number + town) behind a
 * facility profile, compared with what the profile says today.
 */
final readonly class RegisterFact
{
    /**
     * @param  bool  $current  listed in the latest ФЗОМ snapshot
     * @param  bool  $taxNumberMatches  the profile carries the register's tax number (or ФЗО code)
     * @param  bool  $nameMatches  same name words as the register (case, quotes, punctuation aside)
     * @param  bool  $townMatches  same town
     */
    public function __construct(
        public int $recordId,
        public bool $current,
        public bool $taxNumberMatches,
        public bool $nameMatches,
        public bool $townMatches,
    ) {}

    public function agrees(): bool
    {
        return $this->current && $this->taxNumberMatches && $this->nameMatches && $this->townMatches;
    }
}
