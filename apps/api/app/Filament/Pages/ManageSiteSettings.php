<?php

namespace App\Filament\Pages;

use App\Filament\Support\OptimizedImageUpload;
use App\Models\SiteSetting;
use App\Support\Media\BrandingUploadPath;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Site settings';

    protected string $view = 'filament.pages.manage-site-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.update') ?? false;
    }

    public function mount(): void
    {
        $settings = SiteSetting::current();
        $this->data = [
            'registrations_enabled' => $settings->registrations_enabled,
            'require_email_verification' => $settings->require_email_verification,
            'maintenance_mode' => $settings->maintenance_mode,
            'public_guidance' => $settings->public_guidance,
            'public_products' => $settings->public_products,
            'public_pharmacies' => $settings->public_pharmacies,
            'public_forum' => $settings->public_forum,
            'logo_path' => self::brandingStateFromPath($settings->logo_path),
            'favicon_path' => self::brandingStateFromPath($settings->favicon_path),
            'footer_emergency_text' => $settings->footer_emergency_text,
            'footer_disclaimer_text' => $settings->footer_disclaimer_text,
            'copyright_name' => $settings->copyright_name,
            'profile_avatar_min_messages' => $settings->profile_avatar_min_messages,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Branding')
                    ->description('Images are stored under storage/app/public/media locally. Set MEDIA_DISK for production.')
                    ->schema([
                        OptimizedImageUpload::siteBranding('logo_path', 'site/logo')
                            ->label('Website logo'),
                        OptimizedImageUpload::siteBranding('favicon_path', 'site/favicon')
                            ->label('Favicon'),
                    ]),
                Section::make('Footer copy')
                    ->schema([
                        Textarea::make('footer_emergency_text')
                            ->label('Emergency strip')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('footer_disclaimer_text')
                            ->label('Disclaimer paragraph')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('copyright_name')
                            ->label('Copyright holder')
                            ->maxLength(120)
                            ->required(),
                    ]),
                Section::make('Member profile')
                    ->schema([
                        TextInput::make('profile_avatar_min_messages')
                            ->label('Forum messages required to unlock profile photo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                    ]),
                Section::make('Accounts')
                    ->schema([
                        Toggle::make('registrations_enabled')
                            ->label('Public registration enabled'),
                        Toggle::make('require_email_verification')
                            ->label('Require email verification'),
                        Toggle::make('maintenance_mode')
                            ->label('Maintenance mode'),
                    ]),
                Section::make('Public modules')
                    ->description('When disabled, the API returns “coming soon” and the web shows a blurred placeholder.')
                    ->schema([
                        Toggle::make('public_forum')->label('Forum'),
                        Toggle::make('public_guidance')->label('Symptom guidance'),
                        Toggle::make('public_products')->label('Products catalog'),
                        Toggle::make('public_pharmacies')->label('Pharmacies'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $settings = SiteSetting::current();
        $data = $this->form->getState();

        $persist = $data;

        foreach (['logo_path', 'favicon_path'] as $field) {
            $normalized = BrandingUploadPath::normalize($data[$field] ?? null);

            if ($normalized !== null) {
                $persist[$field] = $normalized;
            } elseif (BrandingUploadPath::isCorruptState($data[$field] ?? null)) {
                $persist[$field] = BrandingUploadPath::normalize($settings->{$field});
            } else {
                $persist[$field] = $normalized;
            }
        }

        $settings->update($persist);

        $this->data = [
            ...$data,
            'logo_path' => self::brandingStateFromPath($persist['logo_path'] ?? null),
            'favicon_path' => self::brandingStateFromPath($persist['favicon_path'] ?? null),
        ];

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @return list<string>
     */
    private static function brandingStateFromPath(?string $path): array
    {
        $normalized = BrandingUploadPath::normalize($path);

        return $normalized ? [$normalized] : [];
    }
}
