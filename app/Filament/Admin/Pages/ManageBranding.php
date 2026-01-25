<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Settings\Models\BrandSetting;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ManageBranding extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Branding';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.manage-branding';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getRecord()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        Forms\Components\TextInput::make('app_name')
                            ->label('Application Name')
                            ->helperText('The global name of the SaaS platform.')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(4096)
                            ->formatStateUsing(fn (?string $state): ?string => $state ? Str::after($state, 'storage/') : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? "storage/{$state}" : null)
                            ->helperText('Recommended: 512px square, PNG or JPG. Max 4MB.'),
                    ])
                    ->columns(2),
                Section::make('Theme Template')
                    ->description('Choose the default visual style for the platform.')
                    ->schema([
                        Forms\Components\Select::make('template')
                            ->label('Template')
                            ->options([
                                'default' => 'Default - Modern glassmorphism',
                                'void' => 'Void - Cyberpunk neon',
                                'aurora' => 'Aurora - Northern lights',
                                'prism' => 'Prism - Brutalist geometry',
                                'velvet' => 'Velvet - Luxury editorial',
                                'frost' => 'Frost - Arctic glass',
                                'ember' => 'Ember - Warm fire glow',
                                'ocean' => 'Ocean - Deep sea depths',
                            ])
                            ->default('default')
                            ->native(false)
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Email Branding')
                    ->description('Brand colors used in transactional email templates.')
                    ->schema([
                        Forms\Components\TextInput::make('email_primary_color')
                            ->label('Primary color')
                            ->helperText('Hex color, e.g. #4F46E5')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('email_secondary_color')
                            ->label('Secondary color')
                            ->helperText('Hex color, e.g. #A855F7')
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Section::make('Invoice Defaults')
                    ->description('Default settings for invoices.')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_name')
                            ->label('Company name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('invoice_email')
                            ->label('Billing email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('invoice_address')
                            ->label('Billing address')
                            ->rows(3),
                        Forms\Components\TextInput::make('invoice_tax_id')
                            ->label('Tax ID')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('invoice_footer')
                            ->label('Invoice footer')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = $this->getRecord();
        $record->fill($data);
        $record->save();
        Cache::forget('branding.global');

        Notification::make()
            ->title('Global branding updated.')
            ->success()
            ->send();
    }

    private function getRecord(): BrandSetting
    {
        return BrandSetting::query()->firstOrCreate(['id' => BrandSetting::GLOBAL_ID]);
    }
}
