<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Enums\SystemRoleName;
use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:shipping-admin {email} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Internally provisions the automated default admin via deployment scripts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::firstOrNew(['email' => $email]);
        
        $user->forceFill([
            'name' => 'System Admin',
            'password' => Hash::make($password),
            'is_admin' => true,
            'onboarding_completed_at' => now(),
        ])->save();

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $role = Role::firstOrCreate(['name' => SystemRoleName::Admin->value]);
        $user->assignRole($role);

        $this->info("Admin successfully provisioned/verified as {$email}");
    }
}
