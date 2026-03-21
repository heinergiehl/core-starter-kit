<?php

namespace Tests\Feature\Admin;

use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Filament\Admin\Resources\DiscountResource\Pages\CreateDiscount;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DiscountAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_provider_field_is_not_live(): void
    {
        $this->authenticateAdmin();

        Livewire::test(CreateDiscount::class)
            ->assertFormComponentExists('provider', fn ($component): bool => ! $component->isLive());
    }

    public function test_admin_can_create_a_discount_and_sync_its_provider_id(): void
    {
        $this->authenticateAdmin();

        $runtime = $this->mock(BillingRuntimeProvider::class);
        $runtime->shouldReceive('createDiscount')
            ->once()
            ->andReturn('coupon_save20');

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldReceive('adapter')
            ->once()
            ->with('stripe')
            ->andReturn($runtime);

        Livewire::test(CreateDiscount::class)
            ->fillForm([
                'provider' => 'stripe',
                'code' => 'save20',
                'type' => 'percent',
                'amount' => 20,
                'provider_type' => 'coupon',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $discount = Discount::query()->first();

        $this->assertNotNull($discount);
        $this->assertSame('SAVE20', $discount->code);
        $this->assertSame('coupon_save20', $discount->provider_id);
    }

    public function test_discount_creation_rolls_back_when_provider_sync_fails(): void
    {
        $this->authenticateAdmin();

        $runtime = $this->mock(BillingRuntimeProvider::class);
        $runtime->shouldReceive('createDiscount')
            ->once()
            ->andThrow(new RuntimeException('Sync failed.'));

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldReceive('adapter')
            ->once()
            ->with('stripe')
            ->andReturn($runtime);

        try {
            Livewire::test(CreateDiscount::class)
                ->fillForm([
                    'provider' => 'stripe',
                    'code' => 'save20',
                    'type' => 'percent',
                    'amount' => 20,
                    'provider_type' => 'coupon',
                    'is_active' => true,
                ])
                ->call('create');

            $this->fail('Expected provider sync failure to bubble up.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sync failed.', $exception->getMessage());
        }

        $this->assertCount(0, Discount::query()->get());
    }

    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }
}
