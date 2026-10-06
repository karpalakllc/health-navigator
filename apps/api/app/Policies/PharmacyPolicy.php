<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

/**
 * Pharmacies are Facility rows, so the Gate resolves FacilityPolicy for them and
 * would check `facilities.*`. This policy is deliberately *not* registered with
 * the Gate; PharmacyResource::getAuthorizationResponse() routes every Filament
 * ability through it so the `pharmacies.*` permissions are what actually apply.
 */
class PharmacyPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'pharmacies';
    }
}
