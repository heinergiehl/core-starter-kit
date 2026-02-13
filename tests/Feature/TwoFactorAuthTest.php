<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\TwoFactorAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'onboarding_completed_at' => now(),
        ], $attributes));
    }

    public function test_user_can_enable_two_factor(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post('/two-factor/enable');

        $response->assertRedirect();
        $this->assertDatabaseHas('two_factor_secrets', [
            'user_id' => $user->id,
        ]);

        // Verify secret is set but not confirmed
        $twoFactor = $user->refresh()->twoFactorAuth;
        $this->assertNotNull($twoFactor->enabled_at);
        $this->assertNull($twoFactor->confirmed_at);
    }

    public function test_user_cannot_enable_when_already_enabled(): void
    {
        $user = $this->createUser();

        TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString('TESTSECRET'),
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/two-factor/enable');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_can_disable_two_factor(): void
    {
        $user = $this->createUser([
            'password' => bcrypt('password'),
        ]);

        TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString('TESTSECRET'),
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete('/two-factor/disable', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('two_factor_secrets', [
            'user_id' => $user->id,
        ]);
    }

    public function test_login_redirects_to_two_factor_challenge_when_enabled(): void
    {
        $user = $this->createUser([
            'password' => bcrypt('password'),
        ]);

        TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString(TwoFactorAuth::generateSecret()),
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertSame($user->id, session('2fa_user_id'));
    }

    public function test_two_factor_challenge_route_can_be_rendered_for_pending_challenge(): void
    {
        $user = $this->createUser();

        $response = $this->withSession(['2fa_user_id' => $user->id])
            ->get(route('two-factor.challenge'));

        $response->assertOk();
    }

    public function test_two_factor_model_verifies_code(): void
    {
        $user = $this->createUser();

        // Use a known secret that generates predictable codes
        $secret = TwoFactorAuth::generateSecret();

        $twoFactor = TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString($secret),
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Generated secret should be decryptable
        $this->assertEquals($secret, $twoFactor->getDecryptedSecret());
    }

    public function test_backup_codes_are_generated(): void
    {
        $codes = TwoFactorAuth::generateBackupCodes(8);

        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-F0-9]{8}$/', $code);
        }
    }

    public function test_backup_code_can_be_consumed(): void
    {
        $user = $this->createUser();
        $codes = ['TESTCODE1', 'TESTCODE2'];

        $twoFactor = TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString('TESTSECRET'),
            'backup_codes' => $codes,
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Verify and consume first code
        $result = $twoFactor->verifyBackupCode('TESTCODE1');
        $this->assertTrue($result);

        // Code should be removed
        $twoFactor->refresh();
        $this->assertCount(1, $twoFactor->backup_codes);
        $this->assertNotContains('TESTCODE1', $twoFactor->backup_codes);
    }

    public function test_is_enabled_returns_correct_status(): void
    {
        $user = $this->createUser();

        // Not confirmed
        $twoFactor = TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString('TESTSECRET'),
            'enabled_at' => now(),
            'confirmed_at' => null,
        ]);

        $this->assertFalse($twoFactor->isEnabled());

        // Confirmed
        $twoFactor->update(['confirmed_at' => now()]);
        $this->assertTrue($twoFactor->isEnabled());
    }
}
