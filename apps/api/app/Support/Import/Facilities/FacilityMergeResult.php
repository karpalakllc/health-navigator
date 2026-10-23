<?php

namespace App\Support\Import\Facilities;

/**
 * What FacilityMerger::merge() did: `merged`, `already_merged` (nothing
 * to do) or `refused` with the reason.
 */
final class FacilityMergeResult
{
    public const MERGED = 'merged';

    public const ALREADY_MERGED = 'already_merged';

    public const REFUSED = 'refused';

    /**
     * @param  array<string, int>  $counts
     * @param  list<string>  $fieldsCopied
     * @param  list<int>  $doctorIds
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
        public readonly array $counts = [],
        public readonly array $fieldsCopied = [],
        public readonly array $doctorIds = [],
    ) {}

    /**
     * @param  array<string, int>  $counts
     * @param  list<string>  $fieldsCopied
     * @param  list<int>  $doctorIds
     */
    public static function merged(array $counts, array $fieldsCopied, array $doctorIds): self
    {
        return new self(self::MERGED, null, $counts, $fieldsCopied, $doctorIds);
    }

    public static function alreadyMerged(): self
    {
        return new self(self::ALREADY_MERGED);
    }

    public static function refused(string $reason): self
    {
        return new self(self::REFUSED, $reason);
    }

    public function ok(): bool
    {
        return $this->status !== self::REFUSED;
    }

    public function message(): string
    {
        return match ($this->status) {
            self::MERGED => sprintf(
                'Merged: %d doctor link(s) moved, %d already linked there, %d kept (workplaces locked); fields copied: %s.',
                $this->counts['doctors_moved'] ?? 0,
                $this->counts['doctors_already_linked'] ?? 0,
                $this->counts['doctors_locked'] ?? 0,
                $this->fieldsCopied === [] ? 'none' : implode(', ', $this->fieldsCopied),
            ),
            self::ALREADY_MERGED => 'Already merged into this facility; nothing changed.',
            default => 'Not merged: '.match ($this->reason) {
                'source_missing', 'target_missing' => 'a facility no longer exists.',
                'same_facility' => 'it is the same facility.',
                'merged_elsewhere' => 'it was merged into another facility.',
                'source_deleted' => 'the website facility is deleted.',
                'target_deleted' => 'the register facility is deleted or merged.',
                'pharmacy' => 'pharmacies are not merged here.',
                'source_not_website_only' => 'only a facility created by the website import (with no register record) can be merged away.',
                'target_not_register' => 'the target is itself a website-only facility; pick the register facility.',
                'source_has_reviews' => 'the website facility has reviews; move them by hand first.',
                'source_has_corrections' => 'the website facility has correction requests or reports; settle them first.',
                'migration_missing' => 'the database is not migrated (facilities.merged_into_id).',
                default => (string) $this->reason,
            },
        };
    }
}
