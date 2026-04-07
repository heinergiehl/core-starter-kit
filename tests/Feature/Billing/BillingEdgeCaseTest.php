<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_plan_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/checkout', [
                'price' => 'monthly',
                'provider' => 'stripe',
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_checkout_requires_price_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/checkout', [
                'plan' => 'pro',
                'provider' => 'stripe',
            ])
            ->assertSessionHasErrors('price');
    }

    public function test_checkout_requires_provider_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/checkout', [
                'plan' => 'pro',
                'price' => 'monthly',
            ])
            ->assertSessionHasErrors('provider');
    }

    public function test_checkout_rejects_invalid_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/checkout', [
                'plan' => 'pro',
                'price' => 'monthly',
                'provider' => 'stripe',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_checkout_rejects_non_numeric_custom_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/checkout', [
                'plan' => 'pro',
                'price' => 'monthly',
                'provider' => 'stripe',
                'custom_amount' => 'abc',
            ])
            ->assertSessionHasErrors('custom_amount');
    }

    public function test_subscription_factory_creates_all_statuses(): void
    {
        $active = Subscription::factory()->active()->create();
        $this->assertEquals(SubscriptionStatus::Active, $active->status);

        $canceled = Subscription::factory()->canceled()->create();
        $this->assertEquals(SubscriptionStatus::Canceled, $canceled->status);
        $this->assertNotNull($canceled->canceled_at);

        $trialing = Subscription::factory()->trialing()->create();
        $this->assertEquals(SubscriptionStatus::Trialing, $trialing->status);
        $this->assertNotNull($trialing->trial_ends_at);

        $pastDue = Subscription::factory()->pastDue()->create();
        $this->assertEquals(SubscriptionStatus::PastDue, $pastDue->status);

        $paused = Subscription::factory()->paused()->create();
        $this->assertEquals(SubscriptionStatus::Paused, $paused->status);

        $unpaid = Subscription::factory()->unpaid()->create();
        $this->assertEquals(SubscriptionStatus::Unpaid, $unpaid->status);

        $incomplete = Subscription::factory()->incomplete()->create();
        $this->assertEquals(SubscriptionStatus::Incomplete, $incomplete->status);

        $expired = Subscription::factory()->incompleteExpired()->create();
        $this->assertEquals(SubscriptionStatus::IncompleteExpired, $expired->status);
    }

    public function test_pending_cancellation_subscription_still_grants_access(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->pendingCancellation()->create([
            'user_id' => $user->id,
        ]);

        // pendingCancellation = canceled but ends_at in the future → still active
        $this->assertTrue(
            Subscription::query()
                ->where('user_id', $user->id)
                ->isActive()
                ->exists()
        );
    }

    public function test_order_factory_creates_all_statuses(): void
    {
        $paid = Order::factory()->paid()->create();
        $this->assertEquals(OrderStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);

        $completed = Order::factory()->completed()->create();
        $this->assertEquals(OrderStatus::Completed, $completed->status);

        $pending = Order::factory()->pending()->create();
        $this->assertEquals(OrderStatus::Pending, $pending->status);
        $this->assertNull($pending->paid_at);

        $failed = Order::factory()->failed()->create();
        $this->assertEquals(OrderStatus::Failed, $failed->status);

        $refunded = Order::factory()->refunded()->create();
        $this->assertEquals(OrderStatus::Refunded, $refunded->status);
        $this->assertNotNull($refunded->refunded_at);
    }

    public function test_discount_factory_creates_variants(): void
    {
        $percentage = Discount::factory()->percentage(25)->create();
        $this->assertEquals(25, $percentage->amount);

        $fixed = Discount::factory()->fixed(1000, 'usd')->create();
        $this->assertEquals(1000, $fixed->amount);
        $this->assertEquals('USD', $fixed->currency);

        $expired = Discount::factory()->expired()->create();
        $this->assertFalse($expired->is_active);
    }

    public function test_webhook_event_factory_creates_states(): void
    {
        $received = WebhookEvent::factory()->create();
        $this->assertEquals('received', $received->status);

        $processed = WebhookEvent::factory()->processed()->create();
        $this->assertEquals('processed', $processed->status);
        $this->assertNotNull($processed->processed_at);

        $failed = WebhookEvent::factory()->failed('Test error')->create();
        $this->assertEquals('failed', $failed->status);
        $this->assertEquals('Test error', $failed->error_message);
    }

    public function test_duplicate_webhook_event_id_is_rejected(): void
    {
        $eventId = 'evt_'.fake()->uuid();

        WebhookEvent::factory()->create([
            'provider' => 'stripe',
            'event_id' => $eventId,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        WebhookEvent::factory()->create([
            'provider' => 'stripe',
            'event_id' => $eventId,
        ]);
    }

    public function test_subscription_active_scope_includes_trialing(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->trialing()->create(['user_id' => $user->id]);

        $this->assertTrue(
            Subscription::query()
                ->where('user_id', $user->id)
                ->isActive()
                ->exists()
        );
    }

    public function test_subscription_active_scope_excludes_expired_canceled(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->canceled()->create([
            'user_id' => $user->id,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse(
            Subscription::query()
                ->where('user_id', $user->id)
                ->isActive()
                ->exists()
        );
    }
}
