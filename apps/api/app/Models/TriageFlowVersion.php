<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One imported revision of a v2 flow file. Lifecycle: draft → (clinician
 * review recorded: reviewed) → published → retired when a newer version is
 * published. Definitions are never edited in place: a changed file imports
 * as a new version.
 */
class TriageFlowVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'triage_flow_id',
        'version',
        'status',
        'definition',
        'definition_hash',
        'source_path',
        'lint_report',
        'lint_errors',
        'lint_warnings',
        'review_exempt_reason',
        'published_at',
        'published_by',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'lint_report' => 'array',
            'version' => 'integer',
            'lint_errors' => 'integer',
            'lint_warnings' => 'integer',
            'published_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TriageFlowReview::class)->latest('id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(TriageFlowReview::class)->latestOfMany();
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** A clinician approved this exact version, and nothing asked for changes since. */
    public function isClinicallyApproved(): bool
    {
        $review = $this->relationLoaded('latestReview') ? $this->latestReview : $this->latestReview()->first();

        return $review !== null && $review->decision === TriageFlowReview::DECISION_APPROVED;
    }

    public function title(): string
    {
        return (string) ($this->definition['title'] ?? $this->flow?->title ?? '');
    }
}
