<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Models\ActivityLog;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Identity\Models\TwoFactorAuth;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_login_creates_an_activity_log_entry(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $log = ActivityLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('auth', $log->category);
        $this->assertSame('auth.login_succeeded', $log->event);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame(User::class, $log->subject_type);
        $this->assertSame($user->id, $log->subject_id);
        $this->assertSame(false, $log->metadata['two_factor'] ?? null);
    }

    public function test_login_that_requires_two_factor_creates_a_challenge_log_entry(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString(TwoFactorAuth::generateSecret()),
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'auth',
            'event' => 'auth.login_challenged',
            'actor_id' => $user->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_completing_two_factor_challenge_creates_a_login_success_log_entry(): void
    {
        $user = User::factory()->create();

        TwoFactorAuth::create([
            'user_id' => $user->id,
            'secret' => Crypt::encryptString(TwoFactorAuth::generateSecret()),
            'backup_codes' => ['RECOVERY1'],
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        session()->put('2fa_user_id', $user->id);
        session()->put('2fa_remember', false);

        Livewire::test(TwoFactorChallenge::class)
            ->set('useRecoveryCode', true)
            ->set('recovery_code', 'RECOVERY1')
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $log = ActivityLog::query()
            ->where('event', 'auth.login_succeeded')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame(User::class, $log->subject_type);
        $this->assertSame($user->id, $log->subject_id);
        $this->assertSame(true, $log->metadata['two_factor'] ?? null);
        $this->assertSame('recovery_code', $log->metadata['method'] ?? null);
    }

    public function test_impersonation_start_and_stop_are_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('impersonate.start', ['user' => $target]))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'admin',
            'event' => 'admin.impersonation_started',
            'actor_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $target->id,
        ]);

        $this->post(route('impersonate.stop'))
            ->assertRedirect(route('filament.admin.resources.users.index'));

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'admin',
            'event' => 'admin.impersonation_ended',
            'actor_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $target->id,
        ]);
    }

    public function test_billing_lifecycle_events_are_logged_without_impersonating_the_customer_as_actor(): void
    {
        $customer = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_audit_order',
            'plan_key' => 'starter',
            'status' => OrderStatus::Pending->value,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'billing.order_created',
            'actor_id' => null,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);

        $order->update([
            'status' => OrderStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'billing.order_status_changed',
            'actor_id' => null,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'provider_id' => 'sub_audit_1',
            'plan_key' => 'starter-monthly',
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'billing.subscription_created',
            'actor_id' => null,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
        ]);

        $subscription->update([
            'status' => SubscriptionStatus::PastDue->value,
            'plan_key' => 'growth-monthly',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'billing.subscription_status_changed',
            'actor_id' => null,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'billing.subscription_plan_changed',
            'actor_id' => null,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
        ]);

        $subscription->delete();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'billing.subscription_deleted',
            'actor_id' => null,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
        ]);
    }

    public function test_admin_can_access_the_audit_log_resource(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        ActivityLog::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/activity-logs')
            ->assertOk();
    }
}
