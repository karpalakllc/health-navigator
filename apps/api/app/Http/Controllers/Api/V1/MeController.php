<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ChangeUsername;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => [
                ...(new UserResource($request->user()))->resolve($request),
                // „Мој профил“: the doctor profile staff linked to this
                // account, or null. Drives the account nav entry.
                'managed_doctor' => $this->managedDoctor($request->user()),
            ],
        ]);
    }

    /**
     * @return array{slug: string, full_name: string, is_published: bool}|null
     */
    private function managedDoctor(User $user): ?array
    {
        $doctor = Doctor::managedBy($user);

        if ($doctor === null || ! $user->can('manageOwnProfile', $doctor)) {
            return null;
        }

        return [
            'slug' => (string) $doctor->slug,
            'full_name' => (string) $doctor->full_name,
            'is_published' => (bool) $doctor->is_published,
        ];
    }

    /**
     * Choose or change the public username (the only editable profile field).
     */
    public function updateProfile(UpdateProfileRequest $request, ChangeUsername $changeUsername): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $changeUsername->handle($user, $request->string('username')->toString());

        return ApiResponse::success([
            'user' => (new UserResource($user->fresh()))->resolve($request),
        ]);
    }
}
