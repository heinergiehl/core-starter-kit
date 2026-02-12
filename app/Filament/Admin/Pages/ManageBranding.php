<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Settings\Models\BrandSetting;
use App\Rules\MinimumContrast;
use App\Support\Color\Contrast;
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
        $record = $this->getRecord();
        $data = $record->toArray();
        $data['template'] = filled($record->template) ? $record->template : $this->defaultTemplate();

        $this->form->fill($data);
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
                        Forms\Components\FileUpload::make('favicon_path')
                            ->label('Favicon')
                            ->disk('public')
                            ->directory('branding')
                            ->acceptedFileTypes([
                                'image/png',
                                'image/svg+xml',
                                'image/x-icon',
                                'image/vnd.microsoft.icon',
                            ])
                            ->maxSize(1024)
                            ->formatStateUsing(fn (?string $state): ?string => $state ? Str::after($state, 'storage/') : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? "storage/{$state}" : null)
                            ->helperText('Recommended: square PNG, SVG, or ICO. Max 1MB.'),
                    ])
                    ->columns(2),
                Section::make('Theme Template')
                    ->description('Choose the default visual style for customer-facing pages. The admin panel uses Filament styling.')
                    ->schema([
                        Forms\Components\Select::make('template')
                            ->label('Template')
                            ->options($this->templateOptions())
                            ->default($this->defaultTemplate())
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->helperText('Tip: if you set all Interface Colors below, templates will share that palette and look more similar.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Interface Colors')
                    ->description('Optional brand palette overrides for customer-facing pages.')
                    ->schema([
                        Forms\Components\ColorPicker::make('color_primary')
                            ->label('Primary color')
                            ->hex()
                            ->placeholder('#4F46E5')
                            ->helperText('Buttons, links, and primary highlights. Leave empty to use the template default.')
                            ->rules([
                                'nullable',
                                'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
                                new MinimumContrast('#FFFFFF', 4.5, 'Use a darker primary color so white button text stays readable.'),
                            ])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $this->normalizeHexColor($state)),
                        Forms\Components\ColorPicker::make('color_secondary')
                            ->label('Secondary color')
                            ->hex()
                            ->placeholder('#A855F7')
                            ->helperText('Secondary accents and supporting emphasis. Leave empty to use the template default.')
                            ->rules([
                                'nullable',
                                'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
                                new MinimumContrast('#FFFFFF', 3.5, 'Secondary color must remain readable on light surfaces.'),
                            ])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $this->normalizeHexColor($state)),
                        Forms\Components\ColorPicker::make('color_accent')
                            ->label('Accent color')
                            ->hex()
                            ->placeholder('#EC4899')
                            ->helperText('Optional tertiary accent for gradient treatments. Leave empty to use the template default.')
                            ->rules([
                                'nullable',
                                'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
                                new MinimumContrast('#FFFFFF', 3.5, 'Accent color should support readable white text.'),
                            ])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $this->normalizeHexColor($state)),
                    ])
                    ->columns(3),
                Section::make('Email Branding (Emails Only)')
                    ->description('Used only in transactional email templates. These colors do not change the web app interface.')
                    ->schema([
                        Forms\Components\ColorPicker::make('email_primary_color')
                            ->label('Primary color')
                            ->hex()
                            ->helperText('Hex color, e.g. #4F46E5. Leave empty to use the default email color.')
                            ->rules([
                                'nullable',
                                'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
                                new MinimumContrast('#FFFFFF', 4.5, 'Email primary color must keep button text readable.'),
                            ])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $this->normalizeHexColor($state)),
                        Forms\Components\ColorPicker::make('email_secondary_color')
                            ->label('Secondary color')
                            ->hex()
                            ->helperText('Hex color, e.g. #A855F7. Leave empty to use the default email color.')
                            ->rules([
                                'nullable',
                                'regex:/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
                                new MinimumContrast('#FFFFFF', 3.5, 'Email secondary color must be readable for text links.'),
                            ])
                            ->dehydrateStateUsing(fn (?string $state): ?string => $this->normalizeHexColor($state)),
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
        $selectedTemplate = (string) ($data['template'] ?? '');
        $data['template'] = in_array($selectedTemplate, $this->allowedTemplates(), true)
            ? $selectedTemplate
            : $this->defaultTemplate();

        foreach (['color_primary', 'color_secondary', 'color_accent', 'email_primary_color', 'email_secondary_color'] as $field) {
            $data[$field] = $this->normalizeHexColor($data[$field] ?? null);
        }

        $record = $this->getRecord();
        $record->fill($data);
        $record->save();
        Cache::forget('branding.global');

        Notification::make()
            ->title('Global branding updated.')
            ->success()
            ->send();
    }

    public function resetInterfaceColors(): void
    {
        foreach (['color_primary', 'color_secondary', 'color_accent'] as $field) {
            $this->data[$field] = null;
        }

        $this->form->fill($this->data);

        Notification::make()
            ->title('Interface colors reset.')
            ->body('Template palette defaults are now selected. Click Save changes to persist.')
            ->info()
            ->send();
    }

    public function resetEmailColors(): void
    {
        foreach (['email_primary_color', 'email_secondary_color'] as $field) {
            $this->data[$field] = null;
        }

        $this->form->fill($this->data);

        Notification::make()
            ->title('Email colors reset.')
            ->body('Default email colors are now selected. Click Save changes to persist.')
            ->info()
            ->send();
    }

    private function getRecord(): BrandSetting
    {
        $record = BrandSetting::query()->find(BrandSetting::GLOBAL_ID);

        if (! $record) {
            $record = new BrandSetting;
            $record->forceFill([
                'id' => BrandSetting::GLOBAL_ID,
                'template' => $this->defaultTemplate(),
            ]);
            $record->save();
        }

        if (! filled($record->template)) {
            $record->template = $this->defaultTemplate();
            $record->save();
        }

        return $record;
    }

    private function defaultTemplate(): string
    {
        $configured = (string) config('template.active', 'default');
        $allowed = $this->allowedTemplates();

        if (in_array($configured, $allowed, true)) {
            return $configured;
        }

        if (in_array('default', $allowed, true)) {
            return 'default';
        }

        return $allowed[0] ?? 'default';
    }

    /**
     * @return array<int, string>
     */
    private function allowedTemplates(): array
    {
        return array_keys(config('template.templates', []));
    }

    /**
     * @return array<string, string>
     */
    private function templateOptions(): array
    {
        return collect(config('template.templates', []))
            ->mapWithKeys(function (array $template, string $key): array {
                return [$key => (string) ($template['name'] ?? Str::headline($key))];
            })
            ->all();
    }

    private function normalizeHexColor(?string $value): ?string
    {
        return Contrast::normalizeHex($value);
    }
}
