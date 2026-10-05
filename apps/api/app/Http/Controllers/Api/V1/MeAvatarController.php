<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Support\Media\ImageOptimizer;
use App\Support\Media\InvalidImageException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $previous = $user->avatar_path;

        try {
            $user->update(['avatar_path' => $path]);
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
