<?php

namespace App\Support\Notifications;

use App\Models\Doctor;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Model;

/**
 * The public face of a reviewed profile, as notifications and e-mails name
 * it: kind, slug, name and the web path. Public directory data only.
 */
final class ProfileRef
{
    /**
     * @return array{kind: string, slug: string, name: string, path: string}|null
     */
    public static function for(?Model $profile): ?array
    {
        return match (true) {
            $profile instanceof Doctor => [
                'kind' => 'doctor',
                'slug' => (string) $profile->slug,
                'name' => (string) $profile->full_name,
                'path' => '/doctors/'.$profile->slug,
            ],
            $profile instanceof Facility => [
                'kind' => $profile->isPharmacy() ? 'pharmacy' : 'facility',
                'slug' => (string) $profile->slug,
                'name' => (string) $profile->name,
                'path' => ($profile->isPharmacy() ? '/pharmacies/' : '/facilities/').$profile->slug,
            ],
            default => null,
        };
    }
}
