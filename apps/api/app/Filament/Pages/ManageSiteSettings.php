<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
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
     * @var array<string, bool>
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
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
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
        $settings->update($this->data);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
