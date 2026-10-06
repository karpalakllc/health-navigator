<?php

namespace App\Policies\Support;

use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Stops staff with user- or role-management permissions from granting more
 * than they hold themselves.
 *
 * Without it, anyone with `staff.update` could give themselves (or an
 * accomplice) the Administrator role, or change an administrator's email and
 * password and log in as them; anyone with `roles.update` could add every
 * permission to their own role.
 *
 * The rule for a non-administrator is "never beyond yourself": they may only
 * grant roles and permissions they already hold, may only manage accounts
 * whose permissions are a subset of their own, and may never touch the
 * Administrator role, its holders, or their own role.
 *
 * "Administrator" is the Spatie role — the same test User::isAdmin() makes.
 */
final class PrivilegeHierarchy
{
    public const ADMINISTRATOR_ROLE = RoleCatalog::ADMINISTRATOR;

    public static function isAdministrator(User $user): bool
    {
        return $user->hasRole(self::ADMINISTRATOR_ROLE);
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if (self::isAdministrator($actor)) {
            return true;
        }

        if (self::isAdministrator($target)) {
            return false;
        }

        // Member permissions are rights over the holder's own contributions,
        // not privileges over anyone else. Counting them would make every
        // client unmanageable by staff who (rightly) cannot post reviews.
        return self::holdsAll(
            $actor,
            $target->getAllPermissions()->pluck('name')->diff(PermissionCatalog::member()),
        );
    }

    public static function canGrantRole(User $actor, Role $role): bool
    {
        if (self::isAdministrator($actor)) {
            return true;
        }

        if ($role->name === self::ADMINISTRATOR_ROLE) {
            return false;
        }

        return self::holdsAll($actor, $role->permissions->pluck('name'));
    }

    public static function canEditRole(User $actor, Role $role): bool
    {
        if (self::isAdministrator($actor)) {
            return true;
        }

        if ($role->name === self::ADMINISTRATOR_ROLE || $actor->hasRole($role)) {
            return false;
        }

        return self::canGrantRole($actor, $role);
    }

    /**
     * @return Collection<int, Role>
     */
    public static function grantableRoles(User $actor): Collection
    {
        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => self::canGrantRole($actor, $role))
            ->values();
    }

    /**
     * @param  iterable<int|string>  $roleIds
     */
    public static function canGrantRoleIds(User $actor, iterable $roleIds): bool
    {
        $grantable = self::grantableRoles($actor)->modelKeys();

        foreach ($roleIds as $roleId) {
            if (! in_array((int) $roleId, $grantable, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Permission ids the actor may put on a role: everything for an
     * administrator, otherwise only what the actor already holds.
     *
     * @return list<int>
     */
    public static function grantablePermissionIds(User $actor): array
    {
        if (self::isAdministrator($actor)) {
            return Permission::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $actor->getAllPermissions()->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @param  Collection<int, string>  $permissions
     */
    private static function holdsAll(User $actor, Collection $permissions): bool
    {
        return $permissions->diff($actor->getAllPermissions()->pluck('name'))->isEmpty();
    }
}
