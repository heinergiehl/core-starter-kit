<?php

namespace App\Console\Commands;

use App\Enums\SystemRoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : The admin email address}
                            {--name= : The admin name}';

    protected $description = 'Create a production admin user with a secure password';

    public function handle(): int
    {
        $email = $this->option('email') ?? text(
            label: 'Admin email address',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : 'Please enter a valid email address.',
        );

        $name = $this->option('name') ?? text(
            label: 'Admin name',
            default: 'Admin',
            required: true,
        );

        // Check if user already exists
        $existing = User::where('email', $email)->first();

        if ($existing && $existing->is_admin) {
            $this->error("An admin user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $pw = password(
            label: 'Password (min 12 characters)',
            required: true,
            validate: function (string $value) {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => ['min:12']],
                );

                return $validator->fails()
                    ? $validator->errors()->first('password')
                    : null;
            },
        );

        $pwConfirm = password(label: 'Confirm password', required: true);

        if ($pw !== $pwConfirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        // Create or promote the user
        if ($existing) {
            $existing->forceFill([
                'name' => $name,
                'password' => $pw,
                'is_admin' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
                'onboarding_completed_at' => $existing->onboarding_completed_at ?? now(),
            ])->save();

            $admin = $existing;
            $this->info("Existing user [{$email}] promoted to admin.");
        } else {
            $admin = new User;
            $admin->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $pw,
                'is_admin' => true,
                'email_verified_at' => now(),
                'onboarding_completed_at' => now(),
            ])->save();

            $this->info("Admin user [{$email}] created.");
        }

        // Assign admin role
        $adminRole = Role::where('name', SystemRoleName::Admin->value)->first();

        if ($adminRole) {
            $admin->syncRoles([$adminRole]);
            $this->info('Admin role assigned.');
        } else {
            $this->warn('Admin role not found — run db:seed first to create roles.');
        }

        $this->newLine();
        $this->info('✅ Done! You can now log in at your app URL.');

        return self::SUCCESS;
    }
}
