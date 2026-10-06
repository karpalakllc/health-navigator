<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The Spatie roles the platform ships with, and their default permissions.
 *
 * Spatie roles and permissions are the only authorization source. The legacy
 * `users.role` column is no longer read or written (see the 2026_10_10
 * migrations), and `users.user_kind` only segments accounts into the Staff and
 * Clients admin resources — neither grants anything.
 */
final class RoleCatalog
{
    public const ADMINISTRATOR = 'Administrator';

    public const MODERATOR = 'Moderator';

    public const FORUM_MODERATOR = 'Forum Moderator';

    public const MEMBER = 'Member';

    /**
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        return [
            self::ADMINISTRATOR => PermissionCatalog::administratorDefaults(),
            self::MODERATOR => PermissionCatalog::moderatorDefaults(),
            self::FORUM_MODERATOR => PermissionCatalog::forumModeratorDefaults(),
            self::MEMBER => PermissionCatalog::member(),
        ];
    }

    /**
     * The role, created with its default permissions if it does not exist yet.
     * An existing role is returned untouched: its permissions are whatever an
     * administrator made them.
     */
    public static function ensure(string $name): Role
    {
        $role = Role::findOrCreate($name, 'web');

        if ($role->wasRecentlyCreated) {
            $permissions = self::defaults()[$name] ?? [];

            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $role->syncPermissions($permissions);
        }

        return $role;
    }
}
