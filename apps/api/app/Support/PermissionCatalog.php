<?php

namespace App\Support;

final class PermissionCatalog
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(
            self::core(),
            self::directoryResources(),
            self::taxonomyResources(),
            self::community(),
            self::guidance(),
        );
    }

    /**
     * @return list<string>
     */
    public static function core(): array
    {
        return [
            'admin.access',
            'settings.view',
            'settings.update',
            'analytics.view',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.assign_roles',
        ];
    }

    /**
     * @return list<string>
     */
    public static function directoryResources(): array
    {
        return self::crudPermissions([
            'doctors',
            'facilities',
            'pharmacies',
            'products',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function taxonomyResources(): array
    {
        return self::crudPermissions([
            'specialties',
            'departments',
            'procedures',
            'clinical_interests',
            'languages',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function community(): array
    {
        return array_merge(
            self::crudPermissions(['forum_categories', 'forum_topics', 'forum_posts']),
            [
                'reviews.view',
                'reviews.update',
                'reviews.delete',
                'forum.moderate',
            ],
        );
    }

    /**
     * @return list<string>
     */
    public static function guidance(): array
    {
        return self::crudPermissions(['triage_flows']);
    }

    /**
     * @param list<string> $resources
     * @return list<string>
     */
    private static function crudPermissions(array $resources): array
    {
        $permissions = [];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public static function moderatorDefaults(): array
    {
        $viewOnly = [];

        foreach (['doctors', 'facilities', 'pharmacies', 'products', 'specialties', 'departments', 'procedures', 'clinical_interests', 'languages', 'triage_flows', 'forum_categories'] as $resource) {
            $viewOnly[] = "{$resource}.view";
        }

        return array_merge(
            ['admin.access'],
            $viewOnly,
            [
                'reviews.view',
                'reviews.update',
                'reviews.delete',
                'forum_topics.view',
                'forum_topics.update',
                'forum_posts.view',
                'forum_posts.update',
                'forum.moderate',
            ],
        );
    }

    /**
     * @return list<string>
     */
    public static function forumModeratorDefaults(): array
    {
        return [
            'admin.access',
            'forum_categories.view',
            'forum_topics.view',
            'forum_topics.update',
            'forum_posts.view',
            'forum_posts.update',
            'forum.moderate',
        ];
    }
}
