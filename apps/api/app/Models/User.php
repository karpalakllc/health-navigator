<?php

namespace App\Models;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'user_kind'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'user_kind' => UserKind::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('admin.access');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Administrator')
            || $this->can('settings.update')
            || $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->user_kind === UserKind::Staff;
    }

    public function isClient(): bool
    {
        return $this->user_kind === UserKind::Client;
    }

    public function canModerateForumCategory(ForumCategory $category): bool
    {
        if ($this->can('forum.moderate') && ! $this->hasScopedForumModeration()) {
            return true;
        }

        return $this->moderatedForumCategories()
            ->whereKey($category->getKey())
            ->exists();
    }

    public function hasScopedForumModeration(): bool
    {
        return $this->moderatedForumCategories()->exists();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('user_kind', UserKind::Staff);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeClients(Builder $query): Builder
    {
        return $query->where('user_kind', UserKind::Client);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    /**
     * @return HasMany<ForumPost, $this>
     */
    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    /**
     * @return BelongsToMany<ForumCategory, $this>
     */
    public function moderatedForumCategories(): BelongsToMany
    {
        return $this->belongsToMany(ForumCategory::class, 'forum_category_moderator');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
