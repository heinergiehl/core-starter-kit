<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_canceled_subscription_with_future_end_is_active_for_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        // Dependencies if needed by observers/events, though direct create mostly works
        // Preserving minimal setup to avoid noise
        
        Subscription::create([
            'user_id' => $user->id,
            'status' => \App\Enums\SubscriptionStatus::Canceled,
            'plan_key' => 'pro-monthly',
            'ends_at' => now()->addDays(5),
            'provider' => \App\Enums\BillingProvider::Stripe,
            'provider_id' => 'sub_fake_feature',
            'quantity' => 1,
        ]);

        $this->assertNotNull($user->activeSubscription());
    }

    public function test_canceled_subscription_with_past_end_is_not_active_for_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'status' => \App\Enums\SubscriptionStatus::Canceled,
            'plan_key' => 'pro-monthly',
            'ends_at' => now()->subDay(),
            'provider' => \App\Enums\BillingProvider::Stripe,
            'provider_id' => 'sub_fake_past',
            'quantity' => 1,
        ]);

        $this->assertNull($user->activeSubscription());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('pricing'));
    }
}
