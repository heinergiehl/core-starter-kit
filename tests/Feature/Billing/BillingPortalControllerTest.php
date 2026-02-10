<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPortalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_route_defaults_to_subscription_provider_without_enum_type_errors(): void
    {
        config(['services.stripe.secret' => null]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->actingAs($user)->get(route('billing.portal'));

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('error');
    }
}
