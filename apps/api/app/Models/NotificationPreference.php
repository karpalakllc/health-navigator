<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's e-mail switches (W8-B). No row means the defaults
 * (NotificationType::defaultEnabled); a row is written the first time the
 * member, or an unsubscribe link, changes something.
 *
 * @property int $user_id
 * @property bool $email_enabled
 * @property bool $moderation
 * @property bool $review_reply
 * @property bool $review_helpful
 * @property bool $impact_digest
 * @property Carbon|null $digest_invited_at
 */
class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'email_enabled',
        'moderation',
        'review_reply',
        'review_helpful',
        'impact_digest',
        'digest_invited_at',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'moderation' => 'boolean',
            'review_reply' => 'boolean',
            'review_helpful' => 'boolean',
            'impact_digest' => 'boolean',
            'digest_invited_at' => 'datetime',
        ];
    }

    /** The member's row, or an unsaved one holding the defaults. */
    public static function for(User $user): self
    {
        /** @var self|null $row */
        $row = self::query()->find($user->getKey());

        if ($row !== null) {
            return $row;
        }

        $defaults = new self(['user_id' => $user->getKey(), 'email_enabled' => true]);

        foreach (NotificationType::preferenceTypes() as $type) {
            $defaults->setAttribute($type->value, $type->defaultEnabled());
        }

        return $defaults;
    }

    /** Whether an e-mail of this type may go to the member. */
    public function allowsEmail(NotificationType $type): bool
    {
        if (! $this->email_enabled) {
            return false;
        }

        return $type->isPreference() ? (bool) $this->getAttribute($type->value) : true;
    }

    /**
     * @return array{email_enabled: bool, types: array<string, bool>, digest_invited_at: string|null}
     */
    public function toPayload(): array
    {
        $types = [];

        foreach (NotificationType::preferenceTypes() as $type) {
            $types[$type->value] = (bool) $this->getAttribute($type->value);
        }

        return [
            'email_enabled' => (bool) $this->email_enabled,
            'types' => $types,
            'digest_invited_at' => $this->digest_invited_at?->toIso8601String(),
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
