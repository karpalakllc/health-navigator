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
            self::member(),
            self::usernames(),
            self::doctorAccountsAndAudit(),
            self::imports(),
        );
    }

    /**
     * Everything except the member capabilities: what the Administrator role
     * holds. Staff accounts do not post reviews or forum content by default;
     * an administrator who wants one to can grant the Member role explicitly.
     *
     * @return list<string>
     */
    public static function administratorDefaults(): array
    {
        return array_values(array_diff(self::all(), self::member()));
    }

    /**
     * What a registered client may do on the public site. Held through the
     * Member role, which registration assigns. These are participation rights
     * over the holder's own content, not privileges over other accounts, so
     * PrivilegeHierarchy leaves them out when comparing actor and target.
     *
     * @return list<string>
     */
    public static function member(): array
    {
        return [
            'reviews.create',
            'forum.post',
        ];
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
            'clients.suspend',
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
            self::reportsAndResponses(),
        );
    }

    /**
     * The member report queue (resolve = keep or hide the reported content)
     * and the official response a doctor or facility gives to a review, which
     * staff enter on their behalf. Added after launch, so a migration grants
     * them to the existing built-in roles as well.
     *
     * @return list<string>
     */
    public static function reportsAndResponses(): array
    {
        return [
            'content_reports.view',
            'content_reports.resolve',
            'reviews.respond',
        ];
    }

    /**
     * Doctor accounts (W5-C): assigning a member account to a doctor profile
     * (and handling „Ова е мој профил“ requests), and reading the audit log.
     * Administrator only by default. Approving a doctor's change request
     * needs doctors.update; moderating a doctor's reply needs reviews.respond.
     * Added after launch, so a migration grants them to existing roles too.
     *
     * @return list<string>
     */
    public static function doctorAccountsAndAudit(): array
    {
        return [
            'doctors.assign_owner',
            'audit.view',
        ];
    }

    /**
     * Data import (W6): imports.view reads import runs, their diff summaries
     * and the review queue; imports.manage resolves review items, publishes
     * imported drafts in bulk, locks fields against re-imports and starts a
     * run from the panel. Administrator only by default; added after launch,
     * so a migration grants them to the existing role.
     *
     * @return list<string>
     */
    public static function imports(): array
    {
        return [
            'imports.view',
            'imports.manage',
        ];
    }

    /**
     * @return list<string>
     */
    public static function guidance(): array
    {
        return self::crudPermissions(['triage_flows']);
    }

    /**
     * The blocked and reserved username lists, and renaming a member whose
     * username slipped through (Filament). Administrator only by default;
     * added after launch, so a migration grants it to the existing role.
     *
     * @return list<string>
     */
    public static function usernames(): array
    {
        return [
            'usernames.manage',
        ];
    }

    /**
     * @param  list<string>  $resources
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
            self::reportsAndResponses(),
        );
    }

    /**
     * Forum moderators are community members scoped to categories; the report
     * queue spans reviews too, so it stays with staff.
     *
     * @return list<string>
     */
    public static function forumModeratorDefaults(): array
    {
        return [
            'forum_categories.view',
            'forum_topics.view',
            'forum_topics.update',
            'forum_posts.view',
            'forum_posts.update',
            'forum.moderate',
        ];
    }
}
