<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\EmailAddress;
use App\Support\Media\MediaUrl;
use App\Support\Media\NameInitials;
use App\Support\RoleCatalog;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'user_kind', 'avatar_path'])]
// The second-factor columns are hidden here as well as by Filament's traits, so
// that dropping a trait can never start serialising them.
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    private ?bool $hasScopedForumModeration = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'registration_contested_at' => 'datetime',
            'password' => 'hashed',
            'user_kind' => UserKind::class,
            // Ciphertext under APP_KEY; the recovery codes inside are also hashed.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Stored normalised wherever it is set — API, admin panel, seeders — so that
     * Foo@x and foo@x can never become two accounts. The auth requests normalise
     * too, because their lookups happen before any model is involved.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : EmailAddress::normalize($value),
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->can('admin.access')) {
            return true;
        }

        return $this->can('forum.moderate')
            && (
                $this->can('forum_topics.view')
                || $this->can('forum_posts.view')
                || $this->can('forum_categories.view')
            );
    }

    /**
     * Anyone holding admin.access (Administrator, Moderator) must enrol a second
     * factor before the panel lets them past the set-up page. Community
     * moderators may enrol but are not made to (EnsureStaffMultiFactorAuthentication).
     */
    public function requiresMultiFactorAuthentication(): bool
    {
        return $this->can('admin.access');
    }

    public function hasMultiFactorAuthenticationEnabled(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    /**
     * Enrolling ends every API session for staff: API login refuses accounts
     * that require a second factor (AuthController::login()), and a token minted
     * on the password alone before enrolment would otherwise keep working
     * without it. A community moderator's second factor protects the panel
     * only, so their website sessions are left alone.
     */
    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();

        if (filled($secret) && $this->requiresMultiFactorAuthentication()) {
            $this->revokeApiTokens();
        }
    }

    public function isCommunityModeratorOnly(): bool
    {
        return ! $this->can('admin.access')
            && $this->can('forum.moderate')
            && (
                $this->can('forum_topics.view')
                || $this->can('forum_posts.view')
                || $this->can('forum_categories.view')
            );
    }

    /**
     * The Administrator role and nothing else. The legacy `role` column and
     * holding `settings.update` used to count too; both were things a
     * lower-privileged account could end up with without being an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(RoleCatalog::ADMINISTRATOR);
    }

    /**
     * The coarse account type the API reports as `role` (the web app labels it
     * on the account page). Derived for display only — never authorize on it.
     */
    public function accountRole(): UserRole
    {
        return match (true) {
            $this->isAdmin() => UserRole::Admin,
            $this->isStaff() => UserRole::Moderator,
            default => UserRole::Member,
        };
    }

    /**
     * `user_kind` segments accounts into the Staff and Clients admin resources.
     * It does not grant anything; permissions come from Spatie roles.
     */
    public function isStaff(): bool
    {
        return $this->user_kind === UserKind::Staff;
    }

    public function isClient(): bool
    {
        return $this->user_kind === UserKind::Client;
    }

    public function isForumModerator(): bool
    {
        return $this->hasRole(RoleCatalog::FORUM_MODERATOR);
    }

    /**
     * Drives the `viewer.can_moderate` flag on the topic payload. Delegates to
     * ForumTopicPolicy::update, the single implementation, so the UI never offers
     * a moderation toolbar that the endpoint then rejects.
     */
    public function canModerateForumTopic(ForumTopic $topic): bool
    {
        return $this->can('update', $topic);
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

    /**
     * Memoised: this is consulted at the top of both forum policies, again inside
     * canModerateForumCategory(), and once per query in ForumModerationScope — so an
     * un-cached ->exists() turns every authorization check into extra round trips.
     *
     * The memo lasts as long as the model instance. Anything that changes the
     * assignment within one request (the Filament client form) must call
     * forgetForumModerationScope(), or authorization decisions later in that
     * request are made against the previous assignment.
     */
    public function hasScopedForumModeration(): bool
    {
        return $this->hasScopedForumModeration ??= $this->moderatedForumCategories()->exists();
    }

    public function forgetForumModerationScope(): static
    {
        $this->hasScopedForumModeration = null;
        $this->unsetRelation('moderatedForumCategories');

        return $this;
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

    /**
     * End every API session the account has. The one place this happens, for
     * every way a password can change (reset, an administrator setting it, a
     * contested registration being verified), so none of them can forget it.
     */
    public function revokeApiTokens(): void
    {
        $this->tokens()->delete();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function avatarUrl(): ?string
    {
        return MediaUrl::resolve($this->avatar_path);
    }

    public function avatarInitials(): string
    {
        return NameInitials::from($this->name);
    }

    public function approvedForumPostCount(): int
    {
        return $this->forumPosts()
            ->where('status', ForumContentStatus::Approved)
            ->count();
    }

    public function canChangeAvatar(): bool
    {
        if (! $this->isClient()) {
            return true;
        }

        return $this->approvedForumPostCount() >= self::avatarMinMessages();
    }

    /**
     * One source for the threshold so the gate and the meta the UI shows agree.
     * 0 is a valid setting (Filament allows it) meaning "no requirement" — the
     * old `?: 10` in the meta reported 10 while the gate let everyone through.
     */
    private static function avatarMinMessages(): int
    {
        return (int) (SiteSetting::current()->profile_avatar_min_messages ?? 10);
    }

    /**
     * @return array<string, int|bool>
     */
    public function profileAvatarMeta(): array
    {
        $required = self::avatarMinMessages();

        return [
            'min_messages' => $required,
            'message_count' => $this->approvedForumPostCount(),
            'can_change' => $this->canChangeAvatar(),
        ];
    }
}
