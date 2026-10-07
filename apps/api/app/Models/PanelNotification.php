<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Laravel's `notifications` table: the admin panel's bell (Filament database
 * notifications, e.g. „Објавувањето заврши“ after a background bulk publish).
 * The members' „Известувања“ live in member_notifications (MemberNotification)
 * with their own typed shape; both follow one retention policy: deleted
 * MemberNotification::RETENTION_DAYS after they were created (model:prune,
 * daily), in the account export and deleted with the account.
 */
class PanelNotification extends DatabaseNotification
{
    use MassPrunable;

    /**
     * @return Builder<PanelNotification>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(MemberNotification::RETENTION_DAYS));
    }
}
