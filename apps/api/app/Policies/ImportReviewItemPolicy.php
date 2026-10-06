<?php

namespace App\Policies;

use App\Models\ImportReviewItem;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The import review queue: imports.view reads it, imports.manage acts on it
 * (publish drafts, accept or keep values, hide, dismiss).
 */
class ImportReviewItemPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('imports.view');
    }

    public function view(User $user, ImportReviewItem $item): bool
    {
        return $user->can('imports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportReviewItem $item): bool
    {
        return $user->can('imports.manage');
    }

    public function delete(User $user, ImportReviewItem $item): bool
    {
        return false;
    }
}
