<?php

namespace Database\Factories;

use App\Enums\UserKind;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
            'user_kind' => UserKind::Client,
        ];
    }

    /**
     * A client gets the Member role, as registration gives it; staff states
     * replace it with theirs. Roles are created with their defaults on first
     * use, so this works whether or not a test seeded the roles.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->user_kind === UserKind::Client) {
                $user->assignRole(RoleCatalog::ensure(RoleCatalog::MEMBER));
            }
        });
    }

    public function admin(): static
    {
        return $this->staff(RoleCatalog::ADMINISTRATOR);
    }

    public function moderator(): static
    {
        return $this->staff(RoleCatalog::MODERATOR);
    }

    /**
     * A staff account holding just the given Spatie role, or none at all.
     */
    public function staff(?string $role = null): static
    {
        return $this
            ->state(fn (array $attributes) => ['user_kind' => UserKind::Staff])
            ->afterCreating(function (User $user) use ($role): void {
                $user->syncRoles($role === null ? [] : [RoleCatalog::ensure($role)]);
            });
    }

    /**
     * A client without the Member role: signed up, but not allowed to post.
     */
    public function withoutRoles(): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles([]));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
