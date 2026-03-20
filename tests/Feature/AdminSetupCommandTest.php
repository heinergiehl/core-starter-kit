<?php

namespace Tests\Feature;

use App\Enums\SystemRoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('INITIAL_ADMIN_EMAIL');
        putenv('INITIAL_ADMIN_PASSWORD');
        putenv('INITIAL_ADMIN_NAME');

        parent::tearDown();
    }

    public function test_shipping_admin_command_can_bootstrap_an_admin_from_environment_variables(): void
    {
        putenv('INITIAL_ADMIN_EMAIL=owner@example.com');
        putenv('INITIAL_ADMIN_PASSWORD=super-secret-pass');
        putenv('INITIAL_ADMIN_NAME=Owner');

        $this->artisan('app:shipping-admin --only-if-missing')
            ->expectsOutput('Admin successfully provisioned for owner@example.com.')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'owner@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertTrue(Hash::check('super-secret-pass', (string) $admin->password));
        $this->assertTrue($admin->hasRole(SystemRoleName::Admin->value));
        $this->assertSame('Owner', $admin->name);
    }

    public function test_shipping_admin_command_skips_when_an_admin_already_exists_and_only_if_missing_is_set(): void
    {
        $existingAdmin = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'current-password',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        putenv('INITIAL_ADMIN_EMAIL=owner@example.com');
        putenv('INITIAL_ADMIN_PASSWORD=super-secret-pass');

        $this->artisan('app:shipping-admin --only-if-missing')
            ->expectsOutput('Admin bootstrap skipped because an admin user already exists.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', [
            'email' => 'owner@example.com',
        ]);

        $this->assertTrue(Hash::check('current-password', (string) $existingAdmin->fresh()->password));
    }
}
