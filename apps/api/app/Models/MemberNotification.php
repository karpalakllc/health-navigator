<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line in the member's „Известувања“ (W8-B). `data` carries only what the
 * line shows: the profile's public name and kind/slug, a count, the content
 * kind — never another member's name or a moderator. Deleted 180 days after it
 * was created (model:prune) and with the account.
 *
 * @property int $user_id
 * @property NotificationType $type
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class MemberNotification extends Model
{
    use MassPrunable;

    public const RETENTION_DAYS = 180;

    /**
     * A sent reminder's line names the profile the member meant to review:
     * it goes after 30 days (the reminder row itself is deleted on sending).
     */
    public const REMINDER_RETENTION_DAYS = 30;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<MemberNotification>
     */
    public function prunable(): Builder
    {
        return self::query()->where(fn (Builder $query) => $query
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->orWhere(fn (Builder $reminder) => $reminder
                ->where('type', NotificationType::ReviewReminder->value)
                ->where('created_at', '<', now()->subDays(self::REMINDER_RETENTION_DAYS))));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->getKey(),
            'type' => $this->type->value,
            'data' => $this->data,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
