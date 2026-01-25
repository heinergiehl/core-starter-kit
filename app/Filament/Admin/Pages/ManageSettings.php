<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Settings\Services\AppSettingsService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'App Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(AppSettingsService::class);

        $this->form->fill([
            'support_email' => $settings->get('support.email'),
            'support_discord' => $settings->get('support.discord'),
            'feature_blog' => $settings->get('features.blog', true),
            'feature_roadmap' => $settings->get('features.roadmap', true),
            'feature_announcements' => $settings->get('features.announcements', true),
            'feature_onboarding' => $settings->get('features.onboarding', true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Support Links')
                    ->schema([
                        TextInput::make('support_email')
                            ->label('Support email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('support_discord')
                            ->label('Support Discord URL')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Feature Flags')
                    ->description('Toggle optional sections in the marketing UI.')
                    ->schema([
                        Toggle::make('feature_blog')
                            ->label('Blog'),
                        Toggle::make('feature_roadmap')
                            ->label('Roadmap'),
                        Toggle::make('feature_announcements')
                            ->label('Announcements'),
                        Toggle::make('feature_onboarding')
                            ->label('Onboarding'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(AppSettingsService::class);

        $settings->set('support.email', $data['support_email'] ?? null, 'support');
        $settings->set('support.discord', $data['support_discord'] ?? null, 'support');

        $settings->set('features.blog', (bool) ($data['feature_blog'] ?? true), 'features');
        $settings->set('features.roadmap', (bool) ($data['feature_roadmap'] ?? true), 'features');
        $settings->set('features.announcements', (bool) ($data['feature_announcements'] ?? true), 'features');
        $settings->set('features.onboarding', (bool) ($data['feature_onboarding'] ?? true), 'features');

        Notification::make()
            ->title('Settings updated.')
            ->success()
            ->send();
    }
}
