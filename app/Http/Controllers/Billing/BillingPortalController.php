<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stripe\StripeClient;

class BillingPortalController
{
    public function __invoke(Request $request, ?string $provider = null): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if (!$user || !$team) {
            abort(403);
        }

        $request->user()->can('billing', $team) || abort(403);

        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->latest('id')
            ->first();

        if (!$subscription) {
            return redirect()->route('pricing');
        }

        $provider = $provider ? strtolower($provider) : $subscription->provider;

        if ($provider === 'stripe') {
            return $this->stripePortal($team->id);
        }

        if ($provider === 'lemonsqueezy') {
            return $this->lemonSqueezyPortal($subscription);
        }

        if ($provider === 'paddle') {
            return $this->paddlePortal($subscription);
        }

        app(BillingProviderManager::class)->adapter($provider);

        throw new RuntimeException("Billing portal is not available for provider [{$provider}].");
    }

    private function stripePortal(int $teamId): RedirectResponse
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $customerId = BillingCustomer::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->value('provider_id');

        if (!$customerId) {
            throw new RuntimeException('Stripe customer is not available yet.');
        }

        $client = new StripeClient($secret);
        $session = $client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => route('dashboard', [], true),
        ]);

        return redirect()->away($session->url);
    }

    private function paddlePortal(Subscription $subscription): RedirectResponse
    {
        $urls = data_get($subscription->metadata, 'management_urls', []);
        $portalUrl = $urls['customer_portal']
            ?? $urls['update_payment_method']
            ?? $urls['update']
            ?? $urls['portal']
            ?? data_get($subscription->metadata, 'urls.customer_portal');

        if ($portalUrl) {
            return redirect()->away($portalUrl);
        }

        // Try to fetch from Paddle API
        $apiKey = config('services.paddle.api_key');
        $baseUrl = config('services.paddle.environment') === 'sandbox' 
            ? 'https://sandbox-api.paddle.com' 
            : 'https://api.paddle.com';

        if ($apiKey && $subscription->provider_id) {
            try {
                $response = Http::withToken($apiKey)
                    ->get("{$baseUrl}/subscriptions/{$subscription->provider_id}");

                if ($response->successful()) {
                    $urls = data_get($response->json(), 'data.management_urls') ?? [];
                    $portalUrl = $urls['update_payment_method'] ?? $urls['cancel'] ?? null;

                    if ($portalUrl) {
                        // Update metadata
                        $metadata = $subscription->metadata ?? [];
                        $metadata['management_urls'] = $urls;
                        $subscription->update(['metadata' => $metadata]);

                        return redirect()->away($portalUrl);
                    }
                }
            } catch (\Throwable) {
                // Fall through
            }
        }

        return redirect()->route('billing.index')
            ->with('info', __('Billing portal is being prepared. Please check back shortly or contact support.'));
    }

    private function lemonSqueezyPortal(Subscription $subscription): RedirectResponse
    {
        // First check if portal URL is stored in metadata
        $portalUrl = data_get($subscription->metadata, 'urls.customer_portal')
            ?? data_get($subscription->metadata, 'urls.portal')
            ?? data_get($subscription->metadata, 'urls.customer_portal_url')
            ?? data_get($subscription->metadata, 'urls.update_payment_method');

        if ($portalUrl) {
            return redirect()->away($portalUrl);
        }

        // Try to fetch from LemonSqueezy API
        $apiKey = config('services.lemonsqueezy.api_key');

        if ($apiKey && $subscription->provider_id) {
            try {
                $response = Http::withToken($apiKey)
                    ->withHeaders(['Accept' => 'application/vnd.api+json'])
                    ->get("https://api.lemonsqueezy.com/v1/subscriptions/{$subscription->provider_id}");

                if ($response->successful()) {
                    $urls = $response->json('data.attributes.urls') ?? [];
                    $portalUrl = $urls['customer_portal'] ?? $urls['update_payment_method'] ?? null;

                    if ($portalUrl) {
                        // Cache the URL in metadata for future use
                        $subscription->update([
                            'metadata' => array_merge($subscription->metadata ?? [], ['urls' => $urls]),
                        ]);

                        return redirect()->away($portalUrl);
                    }
                }
            } catch (\Throwable) {
                // Fall through to dashboard redirect
            }
        }

        // Fallback: redirect to dashboard with info message
        return redirect()->route('dashboard')
            ->with('info', __('Billing portal is being prepared. Please check back shortly or contact support.'));
    }
}
