<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A username someone gave up. Private: never in a public payload. It keeps
 * the name out of anyone else's reach until `reserved_until` (six months), so
 * that a member who renames cannot be impersonated under their old name by
 * whoever registers it next. Pruned once the hold is over (model:prune).
 *
 * `reason`: changed (the member renamed), forced (staff renamed it; `note`
 * says why, `changed_by_id` who), anonymised (the account was deleted — the
 * row is then unlinked from it).
 *
 * @property Carbon $reserved_until
 */
class UsernameHistory extends Model
{
    use MassPrunable;

    /** How long a released username stays reserved. */
    public const HOLD_MONTHS = 6;

    protected $table = 'username_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'username',
        'username_normalized',
        'username_skeleton',
        'reason',
        'note',
        'changed_by_id',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'reserved_until' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('reserved_until', '<=', now());
    }

    /**
     * @param  Builder<UsernameHistory>  $query
     * @return Builder<UsernameHistory>
     */
    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('reserved_until', '>', now());
    }
}
