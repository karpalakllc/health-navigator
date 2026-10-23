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
            'maintenance_mode' => $settings->maintenance_mode,
            'maintenance_message' => $settings->maintenance_message,
            'public_guidance' => $settings->public_guidance,
            'public_products' => $settings->public_products,
            'public_pharmacies' => $settings->public_pharmacies,
            'public_forum' => $settings->public_forum,
            'logo_path' => self::brandingStateFromPath($settings->logo_path),
            'favicon_path' => self::brandingStateFromPath($settings->favicon_path),
            'placeholder_doctor_path' => self::brandingStateFromPath($settings->placeholder_doctor_path),
            'placeholder_facility_path' => self::brandingStateFromPath($settings->placeholder_facility_path),
            'placeholder_pharmacy_path' => self::brandingStateFromPath($settings->placeholder_pharmacy_path),
            'forum_rules_enabled' => $settings->forum_rules_enabled,
            'forum_rules_title' => $settings->forum_rules_title,
            'forum_rules_body' => $settings->forum_rules_body,
            'forum_topics_require_moderation' => $settings->forum_topics_require_moderation,
            'forum_posts_require_moderation' => $settings->forum_posts_require_moderation,
            'doctor_replies_require_moderation' => (bool) ($settings->doctor_replies_require_moderation ?? true),
            'reviews_prompt_on_profile' => (bool) $settings->reviews_prompt_on_profile,
            'reviews_prompt_after_search' => (bool) $settings->reviews_prompt_after_search,
            'reviews_share_link' => (bool) $settings->reviews_share_link,
            'reviews_mobile_sticky_cta' => (bool) $settings->reviews_mobile_sticky_cta,
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
                        OptimizedImageUpload::siteBranding('placeholder_doctor_path', 'site/placeholders')
                            ->label('Doctor placeholder'),
                        OptimizedImageUpload::siteBranding('placeholder_facility_path', 'site/placeholders')
                            ->label('Facility placeholder'),
                        OptimizedImageUpload::siteBranding('placeholder_pharmacy_path', 'site/placeholders')
                            ->label('Pharmacy placeholder'),
                    ]),
                Section::make('Footer copy')
                    ->schema([
                        Textarea::make('footer_emergency_text')
                            ->label('Emergency line (optional)')
                            ->helperText('Empty by default: the footer then shows no emergency line.')
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
                        Toggle::make('maintenance_mode')
                            ->label('Maintenance mode'),
                        Textarea::make('maintenance_message')
                            ->label('Maintenance page message')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Forum community')
                    ->schema([
                        Toggle::make('forum_rules_enabled')
                            ->label('Show community rules on forum'),
                        TextInput::make('forum_rules_title')
                            ->label('Rules section title')
                            ->maxLength(255),
                        Textarea::make('forum_rules_body')
                            ->label('Rules body (one rule per line)')
                            ->rows(8)
                            ->columnSpanFull(),
                        Toggle::make('forum_topics_require_moderation')
                            ->label('Require admin approval for new topics')
                            ->helperText('When off, member topics publish immediately. Forum moderators always publish immediately.'),
                        Toggle::make('forum_posts_require_moderation')
                            ->label('Require admin approval for new replies')
                            ->helperText('When off, member replies publish immediately. Forum moderators always publish immediately.'),
                    ]),
                Section::make('Doctor accounts')
                    ->schema([
                        Toggle::make('doctor_replies_require_moderation')
                            ->label('Require admin approval for doctor replies')
                            ->helperText('When on, a reply a linked doctor writes under a review is public only after staff approve it (Community → Doctor replies).'),
                    ]),
                Section::make('Review invitations')
                    ->description('Ways the site invites patients to write the first reviews. They only invite: every review still needs a verified account, one per profile, the rate limits and moderation. Switching one off hides it completely.')
                    ->schema([
                        Toggle::make('reviews_prompt_on_profile')
                            ->label('Prompt on profiles')
                            ->helperText('„Дали сте биле кај …?“ card on doctor, facility and pharmacy profiles after a short read. Once per profile per device; never on the viewer\'s own profile or once they reviewed it.'),
                        Toggle::make('reviews_prompt_after_search')
                            ->label('Prompt after a search')
                            ->helperText('Back on the doctor search results after opening a profile, one calm card asks about that doctor. At most once per session and once a week per device, never again after „Не сега“.'),
                        Toggle::make('reviews_share_link')
                            ->label('„Recommend a review“ share link')
                            ->helperText('A share/copy button on doctor profiles. The link is the same for everyone (profile URL + fixed campaign tags) and carries no reward. Reviews started from it are marked for staff, with statistics consent, and 3+ within 48 h on one profile raise the „Shared-link burst“ flag.'),
                        Toggle::make('reviews_mobile_sticky_cta')
                            ->label('Sticky „Write a review“ button on phones')
                            ->helperText('Adds „Напишете рецензија“ to the bottom bar of doctor profiles that have no published review yet. Phones only.'),
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

        foreach ([
            'logo_path',
            'favicon_path',
            'placeholder_doctor_path',
            'placeholder_facility_path',
            'placeholder_pharmacy_path',
        ] as $field) {
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
            'placeholder_doctor_path' => self::brandingStateFromPath($persist['placeholder_doctor_path'] ?? null),
            'placeholder_facility_path' => self::brandingStateFromPath($persist['placeholder_facility_path'] ?? null),
            'placeholder_pharmacy_path' => self::brandingStateFromPath($persist['placeholder_pharmacy_path'] ?? null),
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
