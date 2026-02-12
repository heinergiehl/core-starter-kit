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

class BillingCancellationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_shows_success_when_subscription_was_already_marked_canceled_locally(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_cancel_partial_success',
            'plan_key' => 'starter',
            'status' => SubscriptionStatus::Active,
        ]);

        $expectedEndsAt = now()->addDays(10)->startOfDay();

        $this->mock(SubscriptionService::class, function (MockInterface $mock) use ($subscription, $expectedEndsAt): void {
            $mock->shouldReceive('cancel')
                ->once()
                ->withArgs(fn (Subscription $candidate): bool => $candidate->is($subscription))
                ->andReturnUsing(function () use ($subscription, $expectedEndsAt): void {
                    $subscription->update([
                        'canceled_at' => now(),
                        'ends_at' => $expectedEndsAt,
                    ]);

                    throw new \RuntimeException('non-blocking post-cancel failure');
                });

            $mock->shouldNotReceive('syncSubscriptionState');
        });

        $response = $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.cancel'), ['confirm' => '1']);

        $response
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertNotNull($subscription->canceled_at);
        $this->assertNotNull($subscription->ends_at);
        $this->assertSame($expectedEndsAt->timestamp, $subscription->ends_at?->timestamp);
    }
}
