<?php

namespace App\Filament\App\Pages;

use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Settings\Models\BrandSetting;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandSettings extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-swatch';

    protected static string | \UnitEnum | null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Branding';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.app.pages.brand-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->form->fill($this->getRecord()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        Forms\Components\TextInput::make('app_name')
                            ->label('Workspace name')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(1024)
                            ->formatStateUsing(fn (?string $state): ?string => $state ? Str::after($state, 'storage/') : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? "storage/{$state}" : null)
                            ->helperText('Recommended: 512px square, PNG or SVG.'),
                    ])
                    ->columns(2),
                Section::make('Colors')
                    ->schema([
                        Forms\Components\ColorPicker::make('color_primary')
                            ->label('Primary')
                            ->formatStateUsing(fn (?string $state): ?string => $this->toHex($state)),
                        Forms\Components\ColorPicker::make('color_secondary')
                            ->label('Secondary')
                            ->formatStateUsing(fn (?string $state): ?string => $this->toHex($state)),
                        Forms\Components\ColorPicker::make('color_accent')
                            ->label('Accent')
                            ->formatStateUsing(fn (?string $state): ?string => $this->toHex($state)),
                        Forms\Components\ColorPicker::make('color_bg')
                            ->label('Background')
                            ->formatStateUsing(fn (?string $state): ?string => $this->toHex($state)),
                        Forms\Components\ColorPicker::make('color_fg')
                            ->label('Foreground')
                            ->formatStateUsing(fn (?string $state): ?string => $this->toHex($state)),
                    ])
                    ->columns(3),
                Section::make('Invoice settings')
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
        $this->authorizeAccess();
        $data = $this->form->getState();

        $record = $this->getRecord();
        $record->fill($data);
        $record->save();

        Notification::make()
            ->title('Branding updated.')
            ->success()
            ->send();
    }

    private function getRecord(): BrandSetting
    {
        $teamId = auth()->user()?->current_team_id;

        return BrandSetting::query()->firstOrCreate([
            'team_id' => $teamId,
        ]);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        $team = $user?->currentTeam;

        if (!$user || !$team) {
            abort(403);
        }

        if (!$team->isOwner($user) && !$team->hasRole($user, TeamRole::Admin)) {
            abort(403);
        }
    }

    private function toHex(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '#')) {
            return $value;
        }

        if (preg_match('/^\d+\s+\d+\s+\d+$/', $value)) {
            [$r, $g, $b] = array_map('intval', preg_split('/\s+/', $value));

            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        if (preg_match('/^\d+,\s*\d+,\s*\d+$/', $value)) {
            [$r, $g, $b] = array_map('intval', preg_split('/\s*,\s*/', $value));

            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        return null;
    }
}
