<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->publicName(),
            'email' => $this->email,
            'role' => $this->accountRole()->value,
            'community_roles' => $this->getRoleNames()->values()->all(),
            'can_moderate_forum' => $this->can('forum.moderate'),
            'avatar_url' => $this->avatarUrl(),
            'avatar_initials' => $this->avatarInitials(),
            'profile_avatar' => $this->profileAvatarMeta(),
        ];
    }
}
