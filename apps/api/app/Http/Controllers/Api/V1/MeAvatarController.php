<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Media\ImageOptimizer;
use App\Support\Media\InvalidImageException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MeAvatarController extends Controller
{
    public function update(Request $request, ImageOptimizer $optimizer): JsonResponse
    {
        $user = $request->user();

        if (! $user->canChangeAvatar()) {
            return ApiResponse::errorCode('avatar.locked', 403);
        }

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        try {
            $path = $optimizer->store($validated['avatar'], 'users/avatars', 512, 512);
        } catch (InvalidImageException) {
            throw ValidationException::withMessages([
                'avatar' => [__('api.avatar.invalid')],
            ]);
        }

        // Store, swap, then delete: the old file goes only once nothing points
        // at it, so a failure at any step leaves the user with a working avatar.
        // The previous path is read under a row lock, not from the in-memory
        // user: two concurrent uploads would otherwise both "replace" the same
        // old file, and the first upload's new file would be orphaned.
        try {
            $previous = DB::transaction(function () use ($user, $path): ?string {
                $previous = User::query()->whereKey($user->getKey())->lockForUpdate()->value('avatar_path');
                $user->update(['avatar_path' => $path]);

                return $previous;
            });
        } catch (Throwable $exception) {
            $optimizer->delete($path);

            throw $exception;
        }

        if ($previous && $previous !== $path) {
            $optimizer->delete($previous);
        }

        return ApiResponse::success([
            'user' => (new UserResource($user->fresh()))->resolve($request),
        ]);
    }
}
