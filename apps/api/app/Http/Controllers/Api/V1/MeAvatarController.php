<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Support\Media\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if ($user->avatar_path) {
            $optimizer->delete($user->avatar_path);
        }

        $path = $optimizer->store($validated['avatar'], 'users/avatars', 512, 512);
        $user->update(['avatar_path' => $path]);

        return ApiResponse::success([
            'user' => (new UserResource($user->fresh()))->resolve($request),
        ]);
    }
}
