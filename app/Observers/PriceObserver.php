<?php

namespace App\Observers;

use App\Domain\Billing\Models\Price;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;

class PriceObserver
{
    /**
     * Handle the Price "deleting" event.
     */
    public function deleting(Price $price): void
    {
        if (empty($price->provider_id) || empty($price->provider)) {
            return;
        }

        try {
            match ($price->provider) {
                'paddle' => $this->archivePaddlePrice($price),
                'stripe' => $this->archiveStripePrice($price),
                'lemonsqueezy' => $this->archiveLemonSqueezyPrice($price),
                default => null,
            };
        } catch (\Throwable $e) {
            // Log error but don't block deletion? 
            // Or should we block? Usually better to fail safely and let local deletion proceed 
            // so we don't get stuck, but logging is important.
            // For now, we'll confirm silently or log if needed.
            report($e);
        }
    }

    private function archivePaddlePrice(Price $price): void
    {
        $apiKey = config('services.paddle.api_key');
        if (!$apiKey) return;

        Http::withToken($apiKey)
            ->acceptJson()
            ->patch("https://api.paddle.com/prices/{$price->provider_id}", [
                'status' => 'archived',
            ]);
    }

    private function archiveStripePrice(Price $price): void
    {
        $secret = config('services.stripe.secret');
        if (!$secret) return;

        $stripe = new StripeClient($secret);
        $stripe->prices->update($price->provider_id, ['active' => false]);
    }

    private function archiveLemonSqueezyPrice(Price $price): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        if (!$apiKey) return;

        // Lemon Squeezy variants can be deleted via API which effectively archives/removes them
        // Check docs: usually DELETE /v1/variants/{id}
        Http::withToken($apiKey)
            ->acceptJson()
            ->delete("https://api.lemonsqueezy.com/v1/variants/{$price->provider_id}");
    }
}
