<?php

namespace App\Models\Concerns;

use App\Enums\ForumContentStatus;
use App\Models\User;
use App\Support\UgcMailer;

trait ModeratesForumContent
{
    public function approve(User $moderator): void
    {
        $this->update([
            'status' => ForumContentStatus::Approved,
            'published_at' => now(),
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => null,
        ]);

        UgcMailer::notifyApproved($this->fresh());
    }

    public function reject(User $moderator, ?string $note = null): void
    {
        $this->update([
            'status' => ForumContentStatus::Rejected,
            'published_at' => null,
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => $note,
        ]);

        UgcMailer::notifyRejected($this->fresh());
    }
}
