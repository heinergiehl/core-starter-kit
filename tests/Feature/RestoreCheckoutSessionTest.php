<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\CheckoutStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreCheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_session_and_redirects_to_clean_url(): void
    {
        $user = User::factory()->create();

        $checkoutSession = CheckoutSession::create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'plan_key' => 'starter',
            'price_key' => 'starter-monthly',
        ]);

        $urls = app(CheckoutService::class)->buildCheckoutUrls('stripe', $checkoutSession);

        $response = $this->get($urls['success']);

        $response->assertRedirect(route('billing.processing'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $checkoutSession->id,
            'status' => CheckoutStatus::Completed->value,
        ]);

        $follow = $this->get(route('billing.processing'));
        $follow->assertViewHas('session_uuid', $checkoutSession->uuid);
    }

    public function test_it_does_not_restore_with_invalid_signature(): void
    {
        $user = User::factory()->create();

        $checkoutSession = CheckoutSession::create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'plan_key' => 'starter',
            'price_key' => 'starter-monthly',
        ]);

        $response = $this->get(route('billing.processing', [
            'session' => $checkoutSession->uuid,
            'sig' => 'invalid',
        ]));

        $response->assertOk();
        $this->assertGuest();
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $checkoutSession->id,
            'status' => CheckoutStatus::Pending->value,
        ]);
    }

    public function test_it_does_not_switch_authenticated_user_when_checkout_belongs_to_another_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $checkoutSession = CheckoutSession::create([
            'user_id' => $owner->id,
            'provider' => 'stripe',
            'plan_key' => 'starter',
            'price_key' => 'starter-monthly',
        ]);

        $urls = app(CheckoutService::class)->buildCheckoutUrls('stripe', $checkoutSession);

        $this->actingAs($other);

        $mismatchResponse = $this->get($urls['success']);

        $mismatchResponse->assertRedirect(route('billing.processing'));
        $this->assertAuthenticatedAs($other);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $checkoutSession->id,
            'status' => CheckoutStatus::Pending->value,
        ]);

        $this->actingAs($owner);

        $ownerResponse = $this->get($urls['success']);

        $ownerResponse->assertRedirect(route('billing.processing'));
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $checkoutSession->id,
            'status' => CheckoutStatus::Completed->value,
        ]);
    }
}
