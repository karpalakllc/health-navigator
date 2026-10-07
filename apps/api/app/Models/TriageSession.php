<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriageSession extends Model
{
    use HasUuids;

    /**
     * No user_id: guidance sessions are never linked to an account. Whoever
     * started a session proves it with the secret token issued at creation.
     */
    public const ENGINE_V1 = 1;

    public const ENGINE_V2 = 2;

    protected $fillable = [
        'engine',
        'triage_flow_id',
        'token_hash',
        'terms_accepted_at',
        'emergency_stopped',
        'outcome_code',
        'outcome_level',
        'completed_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'emergency_stopped' => 'boolean',
            'completed_at' => 'datetime',
            'engine' => 'integer',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Constant-time check. A session without a hash (started before tokens
     * existed) matches nothing, so it cannot be reopened anonymously.
     */
    public function tokenMatches(?string $token): bool
    {
        if ($token === null || $token === '' || ! is_string($this->token_hash)) {
            return false;
        }

        return hash_equals($this->token_hash, self::hashToken($token));
    }

    /** @return BelongsTo<TriageFlow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }

    /** @return HasMany<TriageSessionAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(TriageSessionAnswer::class);
    }

    /**
     * v2: the flows chosen for this session, in the order they run.
     *
     * @return HasMany<TriageSessionFlow, $this>
     */
    public function sessionFlows(): HasMany
    {
        return $this->hasMany(TriageSessionFlow::class)->orderBy('position');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function answersMap(): array
    {
        $map = [];

        foreach ($this->answers as $answer) {
            $map[$answer->step_key] = $answer->values;
        }

        return $map;
    }
}
