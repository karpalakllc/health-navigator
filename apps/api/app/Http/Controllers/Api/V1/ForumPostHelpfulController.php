<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ForumPost;
use App\Support\ForumPostHelpfulVotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * W8-C: „Корисно“ on a published forum reply. PUT marks it, DELETE takes the
 * mark back; both are idempotent and answer with the new count, like
 * ReviewHelpfulController.
 */
class ForumPostHelpfulController extends Controller
{
    public function store(int $post, Request $request): JsonResponse
    {
        $model = $this->publishedReply($post);

        if ((int) $model->user_id === (int) $request->user()->getKey()) {
            throw ValidationException::withMessages([
                'post' => [__('api.forum.helpful_own')],
            ]);
        }

        return ApiResponse::success(ForumPostHelpfulVotes::add($model, $request->user()));
    }

    public function destroy(int $post, Request $request): JsonResponse
    {
        return ApiResponse::success(ForumPostHelpfulVotes::remove($this->publishedReply($post), $request->user()));
    }

    private function publishedReply(int $id): ForumPost
    {
        return ForumPost::query()
            ->approved()
            ->whereKey($id)
            ->whereHas('topic', fn (Builder $query) => $query->visible())
            ->firstOrFail();
    }
}
