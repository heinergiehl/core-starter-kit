<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\SubscriptionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalization_defaults_to_usd_if_missing(): void
    {
        // We use Reflection to test private method or just test dispatchEvents via syncFromProvider logic
        // Easier to test syncFromProvider triggering an event with correct currency

        $user = User::factory()->create();

        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class);

        $service = new SubscriptionService($providerManager, $planService);

        // We can expose the private method via reflection for unit testing precision
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveSubscriptionCurrency');
        $method->setAccessible(true);

        $currency = $method->invokeArgs($service, [['foo' => 'bar']]);

        $this->assertEquals('USD', $currency);
    }

    public function test_normalization_finds_currency_in_metadata(): void
    {
        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class);
        $service = new SubscriptionService($providerManager, $planService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveSubscriptionCurrency');
        $method->setAccessible(true);

        $this->assertEquals('EUR', $method->invokeArgs($service, [['currency' => 'EUR']]));
        $this->assertEquals('GBP', $method->invokeArgs($service, [['items' => ['data' => [['price' => ['currency' => 'GBP']]]]]]));
    }
}
