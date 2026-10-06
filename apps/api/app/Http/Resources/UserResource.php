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
            // The public name everywhere: reviews, forum, the header menu.
            'username' => $this->publicName(),
            // Still a temporary „clen-…“ name: the web asks the member to choose
            // one, and posting waits until they have (UsernameChoice).
            'must_choose_username' => (bool) $this->must_choose_username,
            'username_changed_at' => $this->username_changed_at?->toIso8601String(),
            // Null when the member may change it now (once every 90 days).
            'username_change_available_at' => $this->usernameChangeAvailableAt()?->toIso8601String(),
            // Deprecated alias of `username` for clients built before usernames;
            // the stored display_name is no longer shown anywhere.
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
