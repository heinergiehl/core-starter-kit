<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Settings\Services\AppSettingsService;
use App\Domain\Settings\Services\MailSettingsService;
use App\Mail\TestEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ManageEmailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email Settings';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.admin.pages.manage-email-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(AppSettingsService::class);
        $providerOptions = app(MailSettingsService::class)->providerOptions();
        $selectedProvider = (string) ($settings->get('mail.provider') ?? '');

        if (! array_key_exists($selectedProvider, $providerOptions)) {
            $selectedProvider = (string) array_key_first($providerOptions);
        }

        $this->form->fill([
            'mail_provider' => $selectedProvider,
            'from_address' => $settings->get('mail.from.address'),
            'from_name' => $settings->get('mail.from.name'),
            'ses_region' => $settings->get('mail.ses.region'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Provider')
                    ->schema([
                        Select::make('mail_provider')
                            ->label('Mail provider')
                            ->options(app(MailSettingsService::class)->providerOptions())
                            ->required()
                            ->live()
                            ->native(false),
                        TextInput::make('from_address')
                            ->label('From address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('from_name')
                            ->label('From name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Credential rotation')
                    ->description('Leave secret inputs blank to keep existing credentials. Use these toggles to clear stored secrets.')
                    ->schema([
                        Toggle::make('clear_postmark_token')
                            ->label('Clear Postmark token'),
                        Toggle::make('clear_ses_key')
                            ->label('Clear SES access key'),
                        Toggle::make('clear_ses_secret')
                            ->label('Clear SES secret key'),
                    ])
                    ->columns(2),
                Section::make('Postmark')
                    ->schema([
                        TextInput::make('postmark_token')
                            ->label('Server token')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Leave blank to keep the existing token.'),
                    ])
                    ->visible(fn (Get $get): bool => $get('mail_provider') === 'postmark')
                    ->columns(2),
                Section::make('Amazon SES')
                    ->schema([
                        TextInput::make('ses_key')
                            ->label('Access key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Leave blank to keep the existing key.'),
                        TextInput::make('ses_secret')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Leave blank to keep the existing secret key.'),
                        TextInput::make('ses_region')
                            ->label('Region')
                            ->placeholder('us-east-1')
                            ->required(fn (Get $get): bool => $get('mail_provider') === 'ses')
                            ->maxLength(255),
                    ])
                    ->visible(fn (Get $get): bool => $get('mail_provider') === 'ses')
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(AppSettingsService::class);

        $this->validateProviderSecretRequirements($data, $settings);

        $settings->set('mail.provider', $data['mail_provider'] ?? null, 'mail');
        $settings->set('mail.from.address', $data['from_address'] ?? null, 'mail');
        $settings->set('mail.from.name', $data['from_name'] ?? null, 'mail');

        $this->storeSecret(
            $settings,
            'mail.postmark.token',
            $data['postmark_token'] ?? null,
            (bool) ($data['clear_postmark_token'] ?? false)
        );
        $this->storeSecret(
            $settings,
            'mail.ses.key',
            $data['ses_key'] ?? null,
            (bool) ($data['clear_ses_key'] ?? false)
        );
        $this->storeSecret(
            $settings,
            'mail.ses.secret',
            $data['ses_secret'] ?? null,
            (bool) ($data['clear_ses_secret'] ?? false)
        );
        $settings->set('mail.ses.region', $data['ses_region'] ?? null, 'mail');
        $settings->applyToConfig();
        app(MailSettingsService::class)->applyConfig();

        Notification::make()
            ->title('Email settings updated.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label('Send test email')
                ->closeModalByClickingAway(false) // Prevent accidental closing
                ->form([
                    TextInput::make('to')
                        ->label('Recipient')
                        ->email()
                        ->required()
                        ->default(fn (): ?string => auth()->user()?->email),
                    Textarea::make('message')
                        ->label('Message')
                        ->rows(3)
                        ->default('This is a test email from your SaaS kit.'),
                ])
                ->action(function (array $data): void {
                    app(MailSettingsService::class)->applyConfig();

                    try {
                        Mail::to($data['to'])->send(new TestEmail($data['message'] ?? 'Test email'));
                    } catch (\Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Test email failed.')
                            ->body('Could not send email. Verify provider credentials and check application logs.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Test email sent.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function validateProviderSecretRequirements(array $data, AppSettingsService $settings): void
    {
        $provider = (string) ($data['mail_provider'] ?? '');
        $errors = [];
        $validProviders = array_keys(app(MailSettingsService::class)->providerOptions());

        if (! in_array($provider, $validProviders, true)) {
            $errors['data.mail_provider'] = 'Please select a valid mail provider.';
        }

        if ($provider === 'postmark' && $this->secretMissingForProvider(
            $settings,
            'mail.postmark.token',
            $data['postmark_token'] ?? null,
            (bool) ($data['clear_postmark_token'] ?? false)
        )) {
            $errors['data.postmark_token'] = 'Postmark server token is required when Postmark is selected.';
        }

        if ($provider === 'ses' && $this->secretMissingForProvider(
            $settings,
            'mail.ses.key',
            $data['ses_key'] ?? null,
            (bool) ($data['clear_ses_key'] ?? false)
        )) {
            $errors['data.ses_key'] = 'SES access key is required when Amazon SES is selected.';
        }

        if ($provider === 'ses' && $this->secretMissingForProvider(
            $settings,
            'mail.ses.secret',
            $data['ses_secret'] ?? null,
            (bool) ($data['clear_ses_secret'] ?? false)
        )) {
            $errors['data.ses_secret'] = 'SES secret key is required when Amazon SES is selected.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function secretMissingForProvider(
        AppSettingsService $settings,
        string $key,
        ?string $incomingValue,
        bool $clearRequested
    ): bool {
        if ($incomingValue !== null && trim($incomingValue) !== '') {
            return false;
        }

        if ($clearRequested) {
            return true;
        }

        return ! $settings->has($key);
    }

    private function storeSecret(
        AppSettingsService $settings,
        string $key,
        ?string $value,
        bool $clearRequested = false
    ): void {
        $trimmedValue = $value !== null ? trim($value) : '';

        if ($trimmedValue !== '') {
            $settings->set($key, $trimmedValue, 'mail', null, true);

            return;
        }

        if ($clearRequested) {
            $settings->set($key, null, 'mail', null, true);
        }
    }
}
