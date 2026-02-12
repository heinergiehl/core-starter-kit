<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SubscriptionService;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingSyncPendingPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_plan_change_can_retry_provider_sync(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_sync_pending_retry',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'starter',
            'metadata' => [
                'pending_plan_key' => 'pro',
                'pending_price_key' => 'monthly',
                'pending_provider_price_id' => 'price_pro_monthly',
            ],
        ]);

        $this->mock(SubscriptionService::class, function (MockInterface $mock) use ($subscription): void {
            $mock->shouldReceive('syncSubscriptionState')
                ->once()
                ->andReturnUsing(function () use ($subscription): Subscription {
                    $subscription->update([
                        'plan_key' => 'pro',
                        'metadata' => [
                            'stripe_price_id' => 'price_pro_monthly',
                        ],
                    ]);

                    return $subscription->fresh();
                });
        });

        $response = $this->actingAs($user)
            ->post(route('billing.sync-pending-plan-change'));

        $response
            ->assertRedirect()
            ->assertSessionHas('success', __('Subscription state synced successfully. Your current plan is now up to date.'));
    }

    public function test_retry_sync_returns_info_when_no_pending_plan_change_exists(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_sync_no_pending',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'starter',
            'metadata' => [
                'stripe_price_id' => 'price_starter_monthly',
            ],
        ]);

        $this->mock(SubscriptionService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('syncSubscriptionState');
        });

        $response = $this->actingAs($user)
            ->post(route('billing.sync-pending-plan-change'));

        $response
            ->assertRedirect()
            ->assertSessionHas('info', __('No pending plan change found to sync.'));
    }

    public function test_retry_sync_returns_error_when_provider_sync_fails(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_sync_provider_failure',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'starter',
            'metadata' => [
                'pending_plan_key' => 'pro',
                'pending_provider_price_id' => 'price_pro_monthly',
            ],
        ]);

        $this->mock(SubscriptionService::class, function (MockInterface $mock) use ($subscription): void {
            $mock->shouldReceive('syncSubscriptionState')
                ->once()
                ->withArgs(fn (Subscription $candidate): bool => $candidate->is($subscription))
                ->andThrow(new \RuntimeException('provider unavailable'));
        });

        $response = $this->actingAs($user)
            ->post(route('billing.sync-pending-plan-change'));

        $response
            ->assertRedirect()
            ->assertSessionHas('error', __('Failed to sync subscription state from your billing provider. Please try again or contact support.'));
    }
}
