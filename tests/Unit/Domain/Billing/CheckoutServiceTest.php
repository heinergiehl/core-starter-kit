<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CheckoutService::class);
    }

    #[Test]
    public function it_creates_user_for_guest()
    {
        Event::fake([Registered::class]);

        $request = new Request;
        $email = 'guest@example.com';
        $name = 'Guest User';

        $result = $this->service->resolveOrCreateUser($request, $email, $name);

        $this->assertTrue($result->created);
        $this->assertInstanceOf(User::class, $result->user);
        $this->assertEquals($email, $result->user->email);
        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    #[Test]
    public function it_reuses_existing_user_without_purchase()
    {
        // Create user with NO purchases (abandoned checkout scenario)
        $user = User::factory()->create(['email' => 'abandoned@example.com']);
        $request = new Request;

        $result = $this->service->resolveOrCreateUser($request, 'abandoned@example.com', 'Abandoned User');

        // Should reuse existing user
        $this->assertFalse($result->created);
        $this->assertEquals($user->id, $result->user->id);
    }

    #[Test]
    public function it_throws_exception_for_user_with_existing_subscription()
    {
        // Create user WITH a subscription
        $user = User::factory()->create(['email' => 'subscribed@example.com']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $request = new Request;

        $this->expectException(BillingException::class);

        $this->service->resolveOrCreateUser($request, 'subscribed@example.com', 'Subscribed User');
    }

    #[Test]
    public function it_returns_existing_user_if_authenticated()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = new Request;
        $request->setUserResolver(fn () => $user);

        $result = $this->service->resolveOrCreateUser($request, 'any@email.com', 'Any Name');

        $this->assertFalse($result->created);
        $this->assertEquals($user->id, $result->user->id);
    }
}
