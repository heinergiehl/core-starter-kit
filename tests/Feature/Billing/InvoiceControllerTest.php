<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_invoice_download_handles_missing_secret_gracefully(): void
    {
        config(['services.stripe.secret' => null]);

        $user = User::factory()->create();

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'txn_test_1',
            'provider_invoice_id' => 'in_test_1',
            'status' => 'paid',
            'amount_due' => 0,
            'amount_paid' => 1000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.download_invoice', $invoice));

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('error');
    }

    public function test_paddle_invoice_download_handles_missing_api_key_gracefully(): void
    {
        config(['services.paddle.api_key' => null]);

        $user = User::factory()->create();

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'paddle',
            'provider_id' => 'txn_test_2',
            'status' => 'paid',
            'amount_due' => 0,
            'amount_paid' => 1000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.download_invoice', $invoice));

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('error');
    }
}
