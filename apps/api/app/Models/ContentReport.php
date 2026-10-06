<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use Database\Factories\ContentReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * A member's report of a published review, forum topic or forum reply. One per
 * reporter per item. Moderators resolve every open report on an item at once:
 * „keep“ leaves the content up, „hide“ unpublishes it through the same
 * rejection path pre-moderation uses (status, moderated_by_id, moderated_at,
 * the note and the author's email).
 *
 * @property ReportReason $reason
 * @property ReportStatus $status
 */
class ContentReport extends Model
{
    /** @use HasFactory<ContentReportFactory> */
    use HasFactory;

    /**
     * The models a member can report. Keys are the short names the admin panel
     * shows; morph types stay class names, as on reviews.
     *
     * @var array<string, class-string<Model>>
     */
    public const REPORTABLE_TYPES = [
        'review' => Review::class,
        'forum_topic' => ForumTopic::class,
        'forum_post' => ForumPost::class,
    ];

    protected $fillable = [
        'reportable_type',
        'reportable_id',
        'user_id',
        'reason',
        'note',
        'status',
        'resolved_by_id',
        'resolved_at',
        'staff_alerted_at',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
            'staff_alerted_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
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
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /**
     * @param  Builder<ContentReport>  $query
     * @return Builder<ContentReport>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Open);
    }

    /**
     * Open reports about the same item, this one included.
     */
    public function openReportsOnSameContent(): int
    {
        return self::query()
            ->open()
            ->where('reportable_type', $this->reportable_type)
            ->where('reportable_id', $this->reportable_id)
            ->count();
    }

    /**
     * Whether the reported item is still publicly visible, i.e. whether hiding
     * it would change anything.
     */
    public function contentIsPublished(): bool
    {
        $content = $this->reportable;

        return match (true) {
            $content instanceof Review => $content->status === ReviewStatus::Approved,
            $content instanceof ForumTopic, $content instanceof ForumPost => $content->status === ForumContentStatus::Approved,
            default => false,
        };
    }

    /**
     * Unpublish the reported item and close every open report on it.
     *
     * The author is told through the usual rejection email, with the note (a
     * neutral default when the moderator leaves it empty), which doubles as the
     * statement of reasons in docs/notice-and-action.md.
     */
    public function hideContent(User $moderator, ?string $note = null): void
    {
        $note = filled($note) ? trim((string) $note) : __('api.report.hidden_default_note', [], 'mk');

        DB::transaction(function () use ($moderator, $note): void {
            $content = $this->reportable;

            if ($this->contentIsPublished()) {
                if ($content instanceof Review) {
                    $content->reject($moderator, $note);
                } elseif ($content instanceof ForumPost) {
                    $content->reject($moderator, $note);
                    $content->topic?->recordRemovedReply();
                } elseif ($content instanceof ForumTopic) {
                    $content->reject($moderator, $note);
                }
            }

            $this->closeOpenReports(ReportStatus::Hidden, $moderator);
        });
    }

    /**
     * Leave the item up and close every open report on it.
     */
    public function keepContent(User $moderator): void
    {
        $this->closeOpenReports(ReportStatus::Kept, $moderator);
    }

    private function closeOpenReports(ReportStatus $outcome, User $moderator): void
    {
        self::query()
            ->open()
            ->where('reportable_type', $this->reportable_type)
            ->where('reportable_id', $this->reportable_id)
            ->update([
                'status' => $outcome->value,
                'resolved_by_id' => $moderator->getKey(),
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        $this->refresh();
    }
}
