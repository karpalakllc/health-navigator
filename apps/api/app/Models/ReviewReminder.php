<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * „Потсети ме за 2 недели“: the member asked to be e-mailed once about
 * reviewing a profile. Holds only who, which profile and when; deleted when
 * the e-mail is sent or no longer needed (reviews:send-reminders), when the
 * member cancels it, by the reminder e-mail's unsubscribe link, and with the
 * account (cascade, and AnonymiseUser).
 *
 * @property int $user_id
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property Carbon $remind_at
 * @property Carbon|null $created_at
 */
class ReviewReminder extends Model
{
    public const DELAY_DAYS = 14;

    /** Pending reminders one member may hold at a time. */
    public const MAX_PENDING = 20;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'remind_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'reviewable_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
