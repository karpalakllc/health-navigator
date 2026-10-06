<?php

use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie becomes the only authorization source. Until now the legacy
 * `users.role` column also granted things: `role = member` was what let an
 * account post reviews and forum content (the `role:member` middleware and the
 * create policies), and `role = admin` made User::isAdmin() true. Both now come
 * from Spatie roles, so every account the column was granting something to is
 * given the equivalent role here, once:
 *
 *  - `member` → the new Member role (reviews.create, forum.post);
 *  - `admin` without the Administrator role → Administrator. Only an
 *    administrator could set that column value (PrivilegeHierarchy), and every
 *    reseed already synced such accounts to Administrator;
 *  - staff `moderator` with no role at all → Moderator, which is what the
 *    roles seeder did for role-less staff on every run.
 *
 * Additive only: nobody loses a role they hold. Re-running is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $member = RoleCatalog::ensure(RoleCatalog::MEMBER);

        $this->grant(
            DB::table('users')->where('role', 'member'),
            $member->getKey(),
        );

        $this->grant(
            DB::table('users')->where('role', 'admin'),
            fn (): int|string => RoleCatalog::ensure(RoleCatalog::ADMINISTRATOR)->getKey(),
        );

        $this->grant(
            DB::table('users')
                ->where('role', 'moderator')
                ->where('user_kind', 'staff')
                ->whereNotExists(fn ($query) => $query
                    ->from(config('permission.table_names.model_has_roles'))
                    ->whereColumn(config('permission.column_names.model_morph_key'), 'users.id')
                    ->where('model_type', (new User)->getMorphClass())),
            fn (): int|string => RoleCatalog::ensure(RoleCatalog::MODERATOR)->getKey(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The Member role and its permissions go; the Administrator and Moderator
     * assignments stay, because they may predate this migration and the old
     * code would have granted them anyway.
     */
    public function down(): void
    {
        $roles = config('permission.table_names.roles');
        $permissions = config('permission.table_names.permissions');

        DB::table($roles)->where('name', RoleCatalog::MEMBER)->where('guard_name', 'web')->delete();
        DB::table($permissions)->whereIn('name', ['reviews.create', 'forum.post'])->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The role is resolved lazily so that a fresh database (no legacy users)
     * does not get Administrator or Moderator created by a migration.
     *
     * @param  int|string|Closure(): (int|string)  $roleId
     */
    private function grant(Builder $users, int|string|Closure $roleId): void
    {
        $pivot = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $modelKey = config('permission.column_names.model_morph_key');
        $morphClass = (new User)->getMorphClass();

        // By id, not offset: the moderator query stops matching rows as they are
        // granted, and offset paging would then skip every other chunk.
        $users->select('users.id')->chunkById(500, function ($rows) use (&$roleId, $pivot, $roleKey, $modelKey, $morphClass): void {
            if ($roleId instanceof Closure) {
                $roleId = $roleId();
            }

            $ids = $rows->pluck('id')->all();

            $existing = DB::table($pivot)
                ->where($roleKey, $roleId)
                ->where('model_type', $morphClass)
                ->whereIn($modelKey, $ids)
                ->pluck($modelKey)
                ->map(fn ($id): string => (string) $id)
                ->all();

            $missing = array_values(array_filter(
                $ids,
                fn ($id): bool => ! in_array((string) $id, $existing, true),
            ));

            DB::table($pivot)->insert(array_map(fn ($id): array => [
                $roleKey => $roleId,
                'model_type' => $morphClass,
                $modelKey => $id,
            ], $missing));
        }, 'users.id', 'id');
    }
};
