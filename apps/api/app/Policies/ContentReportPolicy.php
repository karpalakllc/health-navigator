<?php

namespace App\Policies;

use App\Models\ContentReport;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The admin report queue. Members file reports through the API, never through
 * the panel, and reports are kept as the record of what was decided, so
 * nobody creates or deletes them here.
 */
class ContentReportPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('content_reports.view');
    }

    public function view(User $user, ContentReport $report): bool
    {
        return $user->can('content_reports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Keep or hide. Hiding also changes the reported content, which the
     * queue's hide action checks separately against that content's policy.
     */
    public function update(User $user, ContentReport $report): bool
    {
        return $user->can('content_reports.resolve');
    }

    public function delete(User $user, ContentReport $report): bool
    {
        return false;
    }
}
