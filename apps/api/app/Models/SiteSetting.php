<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'registrations_enabled',
        'require_email_verification',
        'maintenance_mode',
        'public_guidance',
        'public_products',
        'public_pharmacies',
        'public_forum',
    ];

    protected function casts(): array
    {
        return [
            'registrations_enabled' => 'boolean',
            'require_email_verification' => 'boolean',
            'maintenance_mode' => 'boolean',
            'public_guidance' => 'boolean',
            'public_products' => 'boolean',
            'public_pharmacies' => 'boolean',
            'public_forum' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $launchDefaults = [
            'registrations_enabled' => true,
            'require_email_verification' => false,
            'maintenance_mode' => false,
            'public_guidance' => false,
            'public_products' => false,
            'public_pharmacies' => false,
            'public_forum' => true,
        ];

        $testingDefaults = [
            ...$launchDefaults,
            'public_guidance' => true,
            'public_products' => true,
            'public_pharmacies' => true,
        ];

        return static::query()->firstOrCreate(
            [],
            app()->environment('testing') ? $testingDefaults : $launchDefaults,
        );
    }

    /**
     * @return array<string, bool>
     */
    public function publicFlags(): array
    {
        return [
            'public_guidance' => $this->public_guidance,
            'public_products' => $this->public_products,
            'public_pharmacies' => $this->public_pharmacies,
            'public_forum' => $this->public_forum,
            'registrations_enabled' => $this->registrations_enabled,
            'maintenance_mode' => $this->maintenance_mode,
        ];
    }
}
