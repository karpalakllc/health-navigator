<?php

namespace App\Support;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewResponseSource;
use App\Models\AnalyticsEvent;
use App\Models\ContentReport;
use App\Models\ContributorLevel;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\DoctorClaimRequest;
use App\Models\Facility;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\PanelNotification;
use App\Models\ProfileCorrection;
use App\Models\Review;
use App\Models\ReviewAspectRating;
use App\Models\ReviewReminder;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Support\Notifications\ProfileRef;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The member's copy of their own data (D5, right of access / portability), as
 * one JSON document written section by section so memory stays flat however
 * much someone has posted.
 *
 * Strictly the caller's own records. Other people appear only as public
 * directory names (who a review is about) and the titles of approved topics a
 * reply was posted in — never another member's name, a moderator's identity
 * or anyone's unpublished content.
 */
final class AccountExport
{
    public const FORMAT = 'zdravje360.account-export';

    public const VERSION = 1;

    private const CHUNK = 200;

    public function __construct(private readonly User $user) {}

    /**
     * Echoes the document. Called inside a StreamedResponse.
     */
    public function write(): void
    {
        echo '{';
        echo $this->member('format', self::FORMAT).',';
        echo $this->member('version', self::VERSION).',';
        echo $this->member('generated_at', now()->toIso8601String()).',';
        echo $this->member('profile', $this->profile()).',';

        $this->writeList('reviews', $this->reviews(), fn (Review $review): array => $this->review($review));
        echo ',';
        $this->writeList('forum_topics', $this->topics(), fn (ForumTopic $topic): array => $this->topic($topic));
        echo ',';
        $this->writeList('forum_posts', $this->posts(), fn (ForumPost $post): array => $this->post($post));
        echo ',';
        $this->writeList('consents', $this->topics()->whereNotNull('community_rules_accepted_at'), fn (ForumTopic $topic): array => [
            'type' => 'forum_community_rules',
            'description' => 'Прифатени правила на заедницата при отворање тема на форумот.',
            'forum_topic_id' => $topic->getKey(),
            'accepted_at' => $topic->community_rules_accepted_at?->toIso8601String(),
        ]);
        echo ',';
        // Reports the member filed: what, why and the outcome — never who handled it.
        $this->writeList('content_reports', $this->reports(), fn (ContentReport $report): array => [
            'about' => [
                'kind' => array_search($report->reportable_type, ContentReport::REPORTABLE_TYPES, true) ?: null,
                'id' => $report->reportable_id,
            ],
            'reason' => $report->reason->value,
            'note' => $report->note,
            'status' => $report->status->value,
            'created_at' => $report->created_at?->toIso8601String(),
            'resolved_at' => $report->resolved_at?->toIso8601String(),
        ]);
        echo ',';
        // Profile corrections, objections and profile reports sent while
        // signed in: what was asked and its status — not who handled it or
        // the staff note.
        $this->writeList('profile_corrections', ProfileCorrection::query()->where('user_id', $this->user->getKey()), fn (ProfileCorrection $request): array => [
            'type' => $request->type->value,
            'about' => [
                'kind' => $request->subjectKind(),
                'name' => $request->subjectName(),
                'slug' => $request->subject?->getAttribute('slug'),
            ],
            'field' => $request->field?->value,
            'report_reason' => $request->report_reason?->value,
            'message' => $request->message,
            'contact' => $request->contact,
            'status' => $request->status->value,
            'created_at' => $request->created_at?->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeHelpfulVotes();
        echo ',';
        // W8-B: e-mail switches, the in-app notifications and the reminders
        // the member asked for (who, which profile, when — nothing else).
        echo $this->member('notification_preferences', NotificationPreference::for($this->user)->toPayload()).',';
        $this->writeList('notifications', MemberNotification::query()->where('user_id', $this->user->getKey()), fn (MemberNotification $row): array => $row->toPayload());
        echo ',';
        $this->writeList('review_reminders', ReviewReminder::query()->where('user_id', $this->user->getKey())->with('reviewable'), fn (ReviewReminder $reminder): array => [
            'about' => ProfileRef::for($reminder->reviewable),
            'remind_at' => $reminder->remind_at->toIso8601String(),
            'created_at' => $reminder->created_at?->toIso8601String(),
        ]);
        echo ',';
        // The admin panel's bell (staff accounts): title, text, when, read.
        $this->writeList('panel_notifications', PanelNotification::query()->where('notifiable_type', $this->user->getMorphClass())->where('notifiable_id', $this->user->getKey()), fn (PanelNotification $row): array => [
            'title' => $row->data['title'] ?? null,
            'body' => $row->data['body'] ?? null,
            'created_at' => $row->created_at?->toIso8601String(),
            'read_at' => $row->read_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeForumHelpfulVotes();
        echo ',';
        // W8-C: the levels derived from the member's public content.
        echo $this->member('contributor_levels', $this->contributorLevels()).',';
        $this->writeList('devices', $this->devices(), fn (PersonalAccessToken $token): array => [
            'name' => $token->name,
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeList('activity', $this->activity(), fn (AnalyticsEvent $event): array => [
            'event' => $event->event,
            'properties' => $event->properties,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ]);
        echo ',';
        $this->writeDoctorAccount();

        echo '}';
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        $user = $this->user;

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'username_changed_at' => $user->username_changed_at?->toIso8601String(),
            // Names the member gave up (or staff replaced), while they are
            // still held back from others — never who at staff changed them.
            'previous_usernames' => UsernameHistory::query()
                ->where('user_id', $user->getKey())
                ->orderBy('id')
                ->get()
                ->map(fn (UsernameHistory $entry): array => [
                    'username' => $entry->username,
                    'reason' => $entry->reason,
                    'note' => $entry->note,
                    'changed_at' => $entry->created_at?->toIso8601String(),
                    'reserved_until' => $entry->reserved_until->toIso8601String(),
                ])
                ->all(),
            // The public name before usernames (no longer shown; kept until dropped).
            'display_name' => $user->display_name,
            // The sign-up consent: „14+ and I accept the Terms of Use and the
            // Privacy Policy“. Null for accounts created before it was asked.
            'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            'terms_version' => $user->terms_version,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'avatar_url' => $user->avatarUrl(),
            'roles' => $user->getRoleNames()->values()->all(),
            'moderated_forum_categories' => $user->moderatedForumCategories()->pluck('slug')->values()->all(),
        ];
    }

    /**
     * @return Builder<Review>
     */
    private function reviews(): Builder
    {
        return Review::query()->where('user_id', $this->user->getKey())->with(['reviewable', 'aspectRatings']);
    }

    /**
     * @return array<string, mixed>
     */
    private function review(Review $review): array
    {
        $reviewable = $review->reviewable;

        return [
            'id' => $review->getKey(),
            'about' => match (true) {
                $reviewable instanceof Doctor => ['kind' => 'doctor', 'name' => $reviewable->full_name, 'slug' => $reviewable->slug],
                $reviewable instanceof Facility => [
                    'kind' => $reviewable->isPharmacy() ? 'pharmacy' : 'facility',
                    'name' => $reviewable->name,
                    'slug' => $reviewable->slug,
                ],
                default => null,
            },
            'rating' => $review->rating,
            // Aspect => 1–5, for the aspects the member rated.
            'aspects' => $review->aspectRatings
                ->mapWithKeys(fn (ReviewAspectRating $aspect): array => [$aspect->aspect->value => $aspect->rating])
                ->all(),
            'body' => $review->body,
            'status' => $review->status->value,
            'rejection_note' => $review->rejection_note,
            // Taken down after publication: when, and under which category.
            'removed_at' => $review->removed_at?->toIso8601String(),
            'removal_category' => $review->removal_category?->value,
            // Edited and resent after a refusal (at most once).
            'resubmission_count' => (int) $review->resubmission_count,
            'resubmitted_at' => $review->resubmitted_at?->toIso8601String(),
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
            'published_at' => $review->published_at?->toIso8601String(),
            // W8-B impact counts (no personal data about anyone else).
            'view_count' => (int) $review->view_count,
            'helpful_count' => (int) $review->helpful_count,
        ];
    }

    /**
     * @return Builder<ForumTopic>
     */
    private function topics(): Builder
    {
        return ForumTopic::query()->where('user_id', $this->user->getKey())->with('category');
    }

    /**
     * @return array<string, mixed>
     */
    private function topic(ForumTopic $topic): array
    {
        return [
            'id' => $topic->getKey(),
            'category' => $topic->category?->slug,
            'title' => $topic->title,
            'body' => $topic->body,
            'status' => $topic->status->value,
            'rejection_note' => $topic->rejection_note,
            'removed_at' => $topic->removed_at?->toIso8601String(),
            'removal_category' => $topic->removal_category?->value,
            'community_rules_accepted_at' => $topic->community_rules_accepted_at?->toIso8601String(),
            'created_at' => $topic->created_at?->toIso8601String(),
            'updated_at' => $topic->updated_at?->toIso8601String(),
            'published_at' => $topic->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<ForumPost>
     */
    private function posts(): Builder
    {
        return ForumPost::query()->where('user_id', $this->user->getKey())->with('topic');
    }

    /**
     * @return array<string, mixed>
     */
    private function post(ForumPost $post): array
    {
        $topic = $post->topic;
        // Another member's topic is named only while it is public.
        $titleVisible = $topic !== null
            && ($topic->user_id === $this->user->getKey() || $topic->status === ForumContentStatus::Approved);

        return [
            'id' => $post->getKey(),
            'forum_topic_id' => $post->forum_topic_id,
            'topic_title' => $titleVisible ? $topic->title : null,
            'body' => $post->body,
            'status' => $post->status->value,
            'rejection_note' => $post->rejection_note,
            'removed_at' => $post->removed_at?->toIso8601String(),
            'removal_category' => $post->removal_category?->value,
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<ContentReport>
     */
    private function reports(): Builder
    {
        return ContentReport::query()->where('user_id', $this->user->getKey());
    }

    /**
     * „Мој профил“: the doctor profile this account manages, its change
     * requests and replies, and its „Ова е мој профил“ requests. Staff who
     * decided them are not named.
     */
    private function writeDoctorAccount(): void
    {
        $managed = Doctor::withTrashed()->where('owner_user_id', $this->user->getKey())->first();

        echo $this->member('managed_doctor', $managed ? [
            'slug' => $managed->slug,
            'full_name' => $managed->full_name,
            'linked_at' => $managed->owner_linked_at?->toIso8601String(),
        ] : null).',';

        $this->writeList('doctor_change_requests', DoctorChangeRequest::query()->where('user_id', $this->user->getKey()), fn (DoctorChangeRequest $request): array => [
            'changes' => $request->changes,
            'message' => $request->message,
            'status' => $request->status->value,
            'rejection_reason' => $request->rejection_reason,
            'created_at' => $request->created_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeList('doctor_claim_requests', DoctorClaimRequest::query()->where('user_id', $this->user->getKey()), fn (DoctorClaimRequest $claim): array => [
            'doctor_slug' => $claim->doctor?->slug,
            'message' => $claim->message,
            'contact' => $claim->contact,
            'status' => $claim->status->value,
            'created_at' => $claim->created_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeList('doctor_replies', Review::query()
            ->where('response_by_id', $this->user->getKey())
            ->where('response_source', ReviewResponseSource::Doctor), fn (Review $review): array => [
                'review_id' => $review->getKey(),
                'body' => $review->response_body,
                'status' => $review->response_status?->value,
                'rejection_note' => $review->response_rejection_note,
                'responded_at' => $review->response_at?->toIso8601String(),
            ]);
    }

    /**
     * „Корисно“ votes have no model (App\Support\ReviewHelpfulVotes writes the
     * rows directly), so they are streamed from the query builder.
     */
    private function writeHelpfulVotes(): void
    {
        echo json_encode('helpful_votes', JSON_THROW_ON_ERROR).':[';

        $first = true;
        $votes = DB::table('review_helpful_votes')
            ->where('user_id', $this->user->getKey())
            ->select(['id', 'review_id', 'created_at']);

        foreach ($votes->lazyById(self::CHUNK) as $vote) {
            echo ($first ? '' : ',').$this->encode([
                'review_id' => (int) $vote->review_id,
                'voted_at' => $vote->created_at === null ? null : Carbon::parse($vote->created_at)->toIso8601String(),
            ]);
            $first = false;
        }

        echo ']';
    }

    /**
     * W8-C: „Корисно“ on forum replies (reply id and time).
     */
    private function writeForumHelpfulVotes(): void
    {
        echo json_encode('forum_helpful_votes', JSON_THROW_ON_ERROR).':[';

        $first = true;
        $votes = DB::table('forum_post_helpful_votes')
            ->where('user_id', $this->user->getKey())
            ->select(['id', 'forum_post_id', 'created_at']);

        foreach ($votes->lazyById(self::CHUNK) as $vote) {
            echo ($first ? '' : ',').$this->encode([
                'forum_post_id' => (int) $vote->forum_post_id,
                'voted_at' => $vote->created_at === null ? null : Carbon::parse($vote->created_at)->toIso8601String(),
            ]);
            $first = false;
        }

        echo ']';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contributorLevels(): ?array
    {
        $row = ContributorLevel::query()->find($this->user->getKey());

        if ($row === null) {
            return null;
        }

        return [
            'review_level' => $row->review_level,
            'review_points' => $row->review_points,
            'reviews_count' => $row->reviews_count,
            'review_helpful_count' => $row->review_helpful_count,
            'forum_level' => $row->forum_level,
            'forum_points' => $row->forum_points,
            'forum_topics_count' => $row->forum_topics_count,
            'forum_replies_count' => $row->forum_replies_count,
            'forum_helpful_count' => $row->forum_helpful_count,
            'computed_at' => $row->computed_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    private function devices(): Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $this->user->getMorphClass())
            ->where('tokenable_id', $this->user->getKey());
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function activity(): Builder
    {
        return AnalyticsEvent::query()->where('user_id', $this->user->getKey());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $map
     */
    private function writeList(string $key, Builder $query, callable $map): void
    {
        echo json_encode($key, JSON_THROW_ON_ERROR).':[';

        $first = true;

        foreach ($query->lazyById(self::CHUNK) as $model) {
            echo ($first ? '' : ',').$this->encode($map($model));
            $first = false;
        }

        echo ']';
    }

    private function member(string $key, mixed $value): string
    {
        return json_encode($key, JSON_THROW_ON_ERROR).':'.$this->encode($value);
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
