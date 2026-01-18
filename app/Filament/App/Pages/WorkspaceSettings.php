<?php

namespace App\Filament\App\Pages;

use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Organization\Models\Team;
use App\Domain\Tenancy\Services\TenantProvisioner;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Domain\Organization\Services\TenantDomainValidator;

class WorkspaceSettings extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string | \UnitEnum | null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Domains';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.app.pages.workspace-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->form->fill($this->getRecord()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $baseDomain = config('saas.tenancy.base_domain');

        $subdomainHelper = $baseDomain
            ? "Used for {$baseDomain}. Leave blank to rely on the slug."
            : 'Used for subdomains. Set TENANCY_BASE_DOMAIN to enable subdomain routing.';

        $validator = app(TenantDomainValidator::class);
        $team = $this->getRecord();

        return $schema
            ->schema([
                Section::make('Workspace domains')
                    ->description('Choose how your workspace is reached.')
                    ->schema([
                        Forms\Components\TextInput::make('subdomain')
                            ->label('Subdomain')
                            ->maxLength(63)
                            ->helperText($subdomainHelper)
                            ->rule(function (): callable {
                                return function (string $attribute, mixed $value, callable $fail) use ($validator, $team): void {
                                    $message = $validator->validateSubdomain(is_string($value) ? $value : null);

                                    if ($message) {
                                        $fail($message);
                                        return;
                                    }

                                    if ($validator->subdomainInUse(is_string($value) ? $value : null, $team->tenant_id)) {
                                        $fail('This subdomain is already in use.');
                                    }
                                };
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => $validator->normalize($state)),
                        Forms\Components\TextInput::make('domain')
                            ->label('Custom domain')
                            ->maxLength(255)
                            ->helperText('Optional custom domain like app.acme.com.')
                            ->rule(function (): callable {
                                return function (string $attribute, mixed $value, callable $fail) use ($validator, $team): void {
                                    $message = $validator->validateDomain(is_string($value) ? $value : null);

                                    if ($message) {
                                        $fail($message);
                                        return;
                                    }

                                    if ($validator->domainInUse(is_string($value) ? $value : null, $team->tenant_id)) {
                                        $fail('This domain is already in use.');
                                    }
                                };
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => $validator->normalize($state)),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->authorizeAccess();
        $data = $this->form->getState();

        $team = $this->getRecord();
        $team->fill($data);
        $team->save();
        app(TenantProvisioner::class)->syncDomainsForTeam($team);

        Notification::make()
            ->title('Workspace domains updated.')
            ->success()
            ->send();
    }

    private function getRecord(): Team
    {
        $team = auth()->user()?->currentTeam;

        if (!$team) {
            abort(403);
        }

        return $team;
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

}
