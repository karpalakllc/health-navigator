<?php

namespace App\Support\Licences;

/**
 * What a licence-list file yielded: the rows, and the fragments that could
 * not be read as a row. A failure names only where it was and why — never
 * the text, which holds people's names and ends up in logs.
 */
final class LicenceParseResult
{
    /**
     * @param  list<ParsedLicenceRow>  $rows
     * @param  list<array{reference: string, reason: string}>  $failures
     */
    public function __construct(
        public array $rows = [],
        public array $failures = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            array_merge($this->rows, $other->rows),
            array_merge($this->failures, $other->failures),
        );
    }
}
