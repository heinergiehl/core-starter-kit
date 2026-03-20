<?php

namespace App\Console\Commands;

use App\Enums\SystemRoleName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:shipping-admin
        {--email= : Bootstrap admin email address}
        {--password= : Bootstrap admin password}
        {--name= : Bootstrap admin display name}
        {--only-if-missing : Skip when an admin already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Internally provisions the automated default admin via deployment scripts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?: $this->environmentValue('INITIAL_ADMIN_EMAIL');
        $password = $this->option('password') ?: $this->environmentValue('INITIAL_ADMIN_PASSWORD');
        $name = (string) ($this->option('name') ?: $this->environmentValue('INITIAL_ADMIN_NAME') ?: 'System Admin');
        $onlyIfMissing = (bool) $this->option('only-if-missing');

        if (! $email || ! $password) {
            $this->error('Bootstrap admin email and password must be provided via options or environment variables.');

            return self::FAILURE;
        }

        if ($onlyIfMissing && User::query()->where('is_admin', true)->exists()) {
            $this->info('Admin bootstrap skipped because an admin user already exists.');

            return self::SUCCESS;
        }

        $user = User::firstOrNew(['email' => $email]);
        $userWasRecentlyCreated = ! $user->exists;

        $user->forceFill([
            'name' => $userWasRecentlyCreated ? $name : ($user->name ?: $name),
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'onboarding_completed_at' => now(),
        ])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $role = Role::firstOrCreate([
            'name' => SystemRoleName::Admin->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);
        $user->assignRole($role);

        $this->info("Admin successfully provisioned for {$email}.");

        return self::SUCCESS;
    }

    private function environmentValue(string $key): ?string
    {
        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
