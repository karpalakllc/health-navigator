<?php

namespace App\Support\Import\Names;

/**
 * The outcome of cleaning one name.
 *
 * - `value`: the name with every high-confidence fix applied (the input when
 *   nothing was safe to change);
 * - `changes`: the fix categories applied (for counts and the report);
 * - `uncertain`: a problem the rules see but must not fix alone (a staff
 *   decision), with `suggestion` the proposed value when there is one;
 * - `title`: an academic title found in a person's name, canonical form.
 */
final readonly class CleanedName
{
    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        public string $value,
        public array $changes = [],
        public ?string $uncertain = null,
        public ?string $suggestion = null,
        public ?string $title = null,
    ) {}
}
