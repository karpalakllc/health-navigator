<?php

namespace App\Services\Triage\V2;

final class WalkResult
{
    /**
     * @param  list<string>  $path  answered nodes on the path, in order
     * @param  string|null  $current  the next node to ask, when not finished
     * @param  string|null  $outcome  the outcome id reached (local, or "global:<id>")
     * @param  array<string, int|float>  $scores
     */
    public function __construct(
        public readonly array $path,
        public readonly ?string $current,
        public readonly ?string $outcome,
        public readonly array $scores,
        public readonly bool $broken = false,
    ) {}

    public function finished(): bool
    {
        return $this->outcome !== null;
    }
}
