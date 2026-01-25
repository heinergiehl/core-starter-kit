<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Settings\Services\AppSettingsService;
use App\Domain\Settings\Services\MailSettingsService;
use App\Mail\TestEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Mail;

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

        $this->form->fill([
            'mail_provider' => $settings->get('mail.provider'),
            'from_address' => $settings->get('mail.from.address'),
            'from_name' => $settings->get('mail.from.name'),
            'mailgun_domain' => $settings->get('mail.mailgun.domain'),
            'mailgun_endpoint' => $settings->get('mail.mailgun.endpoint'),
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
                            ->native(false),
                        TextInput::make('from_address')
                            ->label('From address')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('from_name')
                            ->label('From name')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Mailgun')
                    ->schema([
                        TextInput::make('mailgun_domain')
                            ->label('Domain')
                            ->maxLength(255),
                        TextInput::make('mailgun_secret')
                            ->label('Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make('mailgun_endpoint')
                            ->label('Endpoint')
                            ->placeholder('api.mailgun.net')
                            ->maxLength(255),
                    ])
                    ->visible(fn (Get $get): bool => $get('mail_provider') === 'mailgun')
                    ->columns(2),
                Section::make('Postmark')
                    ->schema([
                        TextInput::make('postmark_token')
                            ->label('Server token')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])
                    ->visible(fn (Get $get): bool => $get('mail_provider') === 'postmark')
                    ->columns(2),
                Section::make('Amazon SES')
                    ->schema([
                        TextInput::make('ses_key')
                            ->label('Access key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make('ses_secret')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make('ses_region')
                            ->label('Region')
                            ->placeholder('us-east-1')
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

        $settings->set('mail.provider', $data['mail_provider'] ?? null, 'mail');
        $settings->set('mail.from.address', $data['from_address'] ?? null, 'mail');
        $settings->set('mail.from.name', $data['from_name'] ?? null, 'mail');

        $settings->set('mail.mailgun.domain', $data['mailgun_domain'] ?? null, 'mail');
        $settings->set('mail.mailgun.endpoint', $data['mailgun_endpoint'] ?? null, 'mail');

        $this->setSecretIfFilled($settings, 'mail.mailgun.secret', $data['mailgun_secret'] ?? null);
        $this->setSecretIfFilled($settings, 'mail.postmark.token', $data['postmark_token'] ?? null);
        $this->setSecretIfFilled($settings, 'mail.ses.key', $data['ses_key'] ?? null);
        $this->setSecretIfFilled($settings, 'mail.ses.secret', $data['ses_secret'] ?? null);
        $settings->set('mail.ses.region', $data['ses_region'] ?? null, 'mail');

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
                    Mail::to($data['to'])->send(new TestEmail($data['message'] ?? 'Test email'));

                    Notification::make()
                        ->title('Test email sent.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function setSecretIfFilled(AppSettingsService $settings, string $key, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            return;
        }

        $settings->set($key, $value, 'mail', null, true);
    }
}
