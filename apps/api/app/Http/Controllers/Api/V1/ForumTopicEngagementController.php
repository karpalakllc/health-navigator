<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ForumContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Http\Responses\ApiResponse;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicSubscription;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * What a member does with a topic besides writing in it: follow it
 * („Следи ја темата“), and — as its author or a moderator — mark the most
 * helpful reply („Најкорисен одговор“). Each answers 404 while its admin
 * switch is off.
 */
class ForumTopicEngagementController extends Controller
{
    public function follow(Request $request, string $category, string $topic): JsonResponse
    {
        $this->ensureEnabled('forum_follow_enabled');
        ForumTopicSubscription::follow($this->user($request), $this->visibleTopic($category, $topic));

        return ApiResponse::success(['following' => true]);
    }

    public function unfollow(Request $request, string $category, string $topic): JsonResponse
    {
        $this->ensureEnabled('forum_follow_enabled');
        ForumTopicSubscription::unfollow($this->user($request), $this->visibleTopic($category, $topic));

        return ApiResponse::success(['following' => false]);
    }

    /** The member's followed topics that are still public, latest activity first. */
    public function following(Request $request): JsonResponse
    {
        $this->ensureEnabled('forum_follow_enabled');
        $user = $this->user($request);

        $paginator = ForumTopic::query()
            ->visible()
            ->whereIn('id', ForumTopicSubscription::query()
                ->where('user_id', $user->getKey())
                ->select('forum_topic_id'))
            ->with(['user', 'category'])
            ->orderByDesc('last_post_at')
            ->orderByDesc('id')
            ->paginate(15);

        return ApiResponse::paginated($paginator, ForumTopicSearchResource::collection($paginator));
    }

    public function accept(Request $request, string $category, string $topic): JsonResponse
    {
        $this->ensureEnabled('forum_accepted_answers_enabled');
        $topicModel = $this->visibleTopic($category, $topic);
        $this->ensureCanAccept($this->user($request), $topicModel);

        $validated = $request->validate(['post_id' => ['required', 'integer', 'min:1']]);

        $post = ForumPost::query()
            ->whereKey($validated['post_id'])
            ->where('forum_topic_id', $topicModel->getKey())
            ->where('status', ForumContentStatus::Approved)
            ->first();

        // The author's own follow-up does not answer their question.
        if ($post === null || (int) $post->user_id === (int) $topicModel->user_id) {
            throw ValidationException::withMessages([
                'post_id' => [__('api.forum.accepted_answer_invalid')],
            ]);
        }

        $topicModel->forceFill(['accepted_post_id' => $post->getKey()])->save();

        return ApiResponse::success(['accepted_post_id' => (int) $post->getKey()]);
    }

    public function clear(Request $request, string $category, string $topic): JsonResponse
    {
        $this->ensureEnabled('forum_accepted_answers_enabled');
        $topicModel = $this->visibleTopic($category, $topic);
        $this->ensureCanAccept($this->user($request), $topicModel);

        $topicModel->forceFill(['accepted_post_id' => null])->save();

        return ApiResponse::success(['accepted_post_id' => null]);
    }

    /** The topic's author (still an account) or someone who may moderate it. */
    public static function canAccept(User $user, ForumTopic $topic): bool
    {
        if (! SiteSetting::current()->forumFeatureOn('forum_accepted_answers_enabled')) {
            return false;
        }

        if ((int) $topic->user_id === (int) $user->getKey() && ! $user->isAnonymised()) {
            return true;
        }

        return $user->canModerateForumTopic($topic);
    }

    private function ensureCanAccept(User $user, ForumTopic $topic): void
    {
        abort_unless(self::canAccept($user, $topic), 403);
    }

    private function ensureEnabled(string $flag): void
    {
        abort_unless(SiteSetting::current()->forumFeatureOn($flag), 404);
    }

    private function visibleTopic(string $category, string $topic): ForumTopic
    {
        $categoryModel = ForumCategory::query()->published()->where('slug', $category)->firstOrFail();

        return ForumTopic::query()
            ->where('forum_category_id', $categoryModel->getKey())
            ->approved()
            ->where('slug', $topic)
            ->firstOrFail();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
