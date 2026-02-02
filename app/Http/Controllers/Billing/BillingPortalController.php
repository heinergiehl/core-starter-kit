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

        if (! $user) {
            abort(403);
        }

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return redirect()->route('pricing');
        }

        $provider = $provider ? strtolower($provider) : $subscription->provider;

        if ($provider === 'stripe') {
            return $this->stripePortal($user->id);
        }



        if ($provider === 'paddle') {
            return $this->paddlePortal($subscription);
        }

        app(BillingProviderManager::class)->adapter($provider);

        throw new RuntimeException("Billing portal is not available for provider [{$provider}].");
    }

    private function stripePortal(int $userId): RedirectResponse
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $customerId = BillingCustomer::query()
            ->where('user_id', $userId)
            ->where('provider', 'stripe')
            ->value('provider_id');

        if (! $customerId) {
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

        if ($portalUrl && $this->isAllowedPortalUrl($portalUrl, 'paddle')) {
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

                    if ($portalUrl && $this->isAllowedPortalUrl($portalUrl, 'paddle')) {
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



    private function isAllowedPortalUrl(string $url, string $provider): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        $allowlist = match ($provider) {
            'paddle' => ['paddle.com', 'paddlepay.com'],
            'stripe' => ['stripe.com'],
            default => [],
        };

        foreach ($allowlist as $domain) {
            if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                return true;
            }
        }

        return app()->environment('local');
    }
}
