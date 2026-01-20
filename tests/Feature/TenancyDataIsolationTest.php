<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Models\Team;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Single-Database Tenancy data isolation.
 *
 * These tests verify that the BelongsToTenant trait correctly scopes
 * billing data (subscriptions, orders, invoices) to the current tenant.
 */
class TenancyDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private Team $team1;
    private Team $team2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user to own teams
        $this->user = User::factory()->create();

        // Create two separate tenants
        $this->tenant1 = Tenant::create([]);
        $this->tenant2 = Tenant::create([]);

        // Create teams linked to each tenant
        $this->team1 = Team::factory()->create([
            'owner_id' => $this->user->id,
            'tenant_id' => $this->tenant1->id,
        ]);

        $this->team2 = Team::factory()->create([
            'owner_id' => $this->user->id,
            'tenant_id' => $this->tenant2->id,
        ]);
    }

    protected function tearDown(): void
    {
        // Always end tenancy after tests
        tenancy()->end();
        parent::tearDown();
    }

    public function test_team_has_tenant_relationship(): void
    {
        $this->assertNotNull($this->team1->tenant);
        $this->assertEquals($this->tenant1->id, $this->team1->tenant_id);

        $this->assertNotNull($this->team2->tenant);
        $this->assertEquals($this->tenant2->id, $this->team2->tenant_id);
    }

    public function test_tenant_has_team_relationship(): void
    {
        $this->assertNotNull($this->tenant1->team);
        $this->assertEquals($this->team1->id, $this->tenant1->team->id);
    }

    public function test_subscription_is_scoped_to_tenant(): void
    {
        // Initialize tenant1 context before creating
        tenancy()->initialize($this->tenant1);

        // Create subscription for tenant1 (BelongsToTenant sets tenant_id automatically)
        $subscription1 = Subscription::create([
            'team_id' => $this->team1->id,
            'provider' => 'test',
            'provider_id' => 'sub_tenant1_001',
            'plan_key' => 'starter',
            'status' => 'active',
        ]);

        // Switch to tenant2 and create their subscription
        tenancy()->initialize($this->tenant2);
        $subscription2 = Subscription::create([
            'team_id' => $this->team2->id,
            'provider' => 'test',
            'provider_id' => 'sub_tenant2_001',
            'plan_key' => 'starter',
            'status' => 'active',
        ]);

        // When tenant1 is active, only their subscription should be visible
        tenancy()->initialize($this->tenant1);
        $this->assertEquals(1, Subscription::count());
        $this->assertEquals($subscription1->id, Subscription::first()->id);

        // When tenant2 is active, only their subscription should be visible
        tenancy()->initialize($this->tenant2);
        $this->assertEquals(1, Subscription::count());
        $this->assertEquals($subscription2->id, Subscription::first()->id);

        // Without tenancy, all should be visible
        tenancy()->end();
        $this->assertEquals(2, Subscription::withoutGlobalScopes()->count());
    }

    public function test_order_is_scoped_to_tenant(): void
    {
        // Initialize tenant1 context before creating
        tenancy()->initialize($this->tenant1);

        $order1 = Order::create([
            'team_id' => $this->team1->id,
            'provider' => 'test',
            'provider_id' => 'order_tenant1_001',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'USD',
        ]);

        // Switch to tenant2 and create their order
        tenancy()->initialize($this->tenant2);
        $order2 = Order::create([
            'team_id' => $this->team2->id,
            'provider' => 'test',
            'provider_id' => 'order_tenant2_001',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'USD',
        ]);

        // Verify scoping
        tenancy()->initialize($this->tenant1);
        $this->assertEquals(1, Order::count());
        $this->assertEquals($order1->id, Order::first()->id);

        tenancy()->initialize($this->tenant2);
        $this->assertEquals(1, Order::count());
        $this->assertEquals($order2->id, Order::first()->id);
    }

    public function test_invoice_is_scoped_to_tenant(): void
    {
        // Initialize tenant1 context before creating
        tenancy()->initialize($this->tenant1);

        $invoice1 = Invoice::create([
            'team_id' => $this->team1->id,
            'provider' => 'test',
            'provider_id' => 'inv_tenant1_001',
            'status' => 'paid',
        ]);

        // Switch to tenant2 and create their invoice
        tenancy()->initialize($this->tenant2);
        $invoice2 = Invoice::create([
            'team_id' => $this->team2->id,
            'provider' => 'test',
            'provider_id' => 'inv_tenant2_001',
            'status' => 'paid',
        ]);

        // Verify scoping
        tenancy()->initialize($this->tenant1);
        $this->assertEquals(1, Invoice::count());
        $this->assertEquals($invoice1->id, Invoice::first()->id);

        tenancy()->initialize($this->tenant2);
        $this->assertEquals(1, Invoice::count());
        $this->assertEquals($invoice2->id, Invoice::first()->id);
    }

    public function test_cross_tenant_data_is_not_accessible(): void
    {
        // Initialize tenant1 and create data
        tenancy()->initialize($this->tenant1);
        $subscription = Subscription::create([
            'team_id' => $this->team1->id,
            'provider' => 'test',
            'provider_id' => 'sub_secret_001',
            'plan_key' => 'starter',
            'status' => 'active',
        ]);

        // Try to access from tenant2 context - should not find it
        tenancy()->initialize($this->tenant2);
        $this->assertNull(Subscription::find($subscription->id));

        // Direct query by ID should also be scoped
        $this->assertEquals(0, Subscription::where('id', $subscription->id)->count());
    }
}
