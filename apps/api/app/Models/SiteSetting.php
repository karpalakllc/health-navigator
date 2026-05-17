<?php

namespace App\Models;

use App\Support\Media\BrandingUploadPath;
use App\Support\Media\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public const DEFAULT_FOOTER_EMERGENCY = 'При медицинска итност повикайте 194 или 112 веднаш.';

    public const DEFAULT_FOOTER_DISCLAIMER = 'Корисничките рецензии се модерираат пред објава. Цените во аптеките се референтни податоци од администратор, не понуди за купување на оваа страница. Насоки за симптоми се само информативни.';

    protected $fillable = [
        'registrations_enabled',
        'require_email_verification',
        'maintenance_mode',
        'public_guidance',
        'public_products',
        'public_pharmacies',
        'public_forum',
        'logo_path',
        'favicon_path',
        'footer_emergency_text',
        'footer_disclaimer_text',
        'copyright_name',
        'profile_avatar_min_messages',
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
            'profile_avatar_min_messages' => 'integer',
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
            'footer_emergency_text' => self::DEFAULT_FOOTER_EMERGENCY,
            'footer_disclaimer_text' => self::DEFAULT_FOOTER_DISCLAIMER,
            'copyright_name' => 'Zdravje360',
            'profile_avatar_min_messages' => 10,
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

    /**
     * @return array<string, mixed>
     */
    public function publicBranding(): array
    {
        return [
            'logo_url' => MediaUrl::resolve(BrandingUploadPath::normalize($this->logo_path)),
            'favicon_url' => MediaUrl::resolve(BrandingUploadPath::normalize($this->favicon_path)),
            'footer_emergency_text' => $this->footer_emergency_text ?: self::DEFAULT_FOOTER_EMERGENCY,
            'footer_disclaimer_text' => $this->footer_disclaimer_text ?: self::DEFAULT_FOOTER_DISCLAIMER,
            'copyright_name' => $this->copyright_name ?: 'Zdravje360',
            'profile_avatar_min_messages' => $this->profile_avatar_min_messages ?: 10,
        ];
    }
}
