<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Services\CheckoutService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
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

    /** @test */
    public function it_creates_user_and_team_for_guest()
    {
        Event::fake([Registered::class]);

        $request = new Request();
        $email = 'guest@example.com';
        $name = 'Guest User';

        $result = $this->service->resolveOrCreateUser($request, $email, $name);

        $this->assertTrue($result['created']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals($email, $result['user']->email);
        $this->assertDatabaseHas('users', ['email' => $email]);
        $this->assertDatabaseHas('teams', ['owner_id' => $result['user']->id]);
        
        Event::assertDispatched(Registered::class);
    }

    /** @test */
    public function it_returns_error_if_user_already_exists_for_guest()
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $request = new Request();

        $result = $this->service->resolveOrCreateUser($request, 'existing@example.com', 'Existing User');

        // Should be a RedirectResponse (back with errors)
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertTrue(session()->has('errors'));
    }

    /** @test */
    public function it_returns_existing_user_if_authenticated()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $request = new Request();
        $request->setUserResolver(fn () => $user);

        $result = $this->service->resolveOrCreateUser($request, 'any@email.com', 'Any Name');

        $this->assertFalse($result['created']);
        $this->assertEquals($user->id, $result['user']->id);
    }
}
