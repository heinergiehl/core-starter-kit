<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProviderSeeder extends Seeder
{
    public function run(): void
    {
        PaymentProvider::updateOrCreate(
            ['slug' => 'stripe'],
            [
                'name' => 'Stripe',
                'is_active' => true,
                'configuration' => [
                    'secret_key' => config('services.stripe.secret'),
                    'publishable_key' => config('services.stripe.key'),
                    'webhook_secret' => config('services.stripe.webhook_secret'),
                ],
            ]
        );

        PaymentProvider::updateOrCreate(
            ['slug' => 'paddle'],
            [
                'name' => 'Paddle',
                'is_active' => true,
                'configuration' => [
                    'vendor_id' => config('services.paddle.vendor_id'),
                    'api_key' => config('services.paddle.api_key'),
                    'environment' => config('services.paddle.environment', 'production'),
                    'webhook_secret' => config('services.paddle.webhook_secret'),
                ],
            ]
        );

    }
}
