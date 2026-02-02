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
                    'webhook_secret' => config('services.stripe.webhook.secret'),
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
                    'auth_code' => config('services.paddle.auth_code'),
                    'public_key' => config('services.paddle.public_key'),
                ],
            ]
        );


    }
}
