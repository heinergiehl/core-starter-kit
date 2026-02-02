<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Jobs\ProcessWebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WebhookController
{
    /**
     * Event types to ignore in app-first mode.
     * These are catalog sync events that are not needed when app is source of truth.
     */
    private const IGNORED_EVENTS = [
        'product.created',
        'product.updated',
        'product.deleted',
        'price.created',
        'price.updated',
        'price.deleted',
    ];

    public function __invoke(Request $request, string $provider): Response
    {
        try {
            $adapter = app(BillingProviderManager::class)->runtime($provider);
            $eventData = $adapter->parseWebhook($request);

            // App-first mode: ignore product/price events (return 200 so provider stops retrying)
            if (in_array($eventData->type, self::IGNORED_EVENTS, true)) {
                return response()->noContent();
            }

            $webhookEvent = WebhookEvent::query()->firstOrCreate(
                [
                    'provider' => $provider,
                    'event_id' => $eventData->id,
                ],
                [
                    'type' => $eventData->type,
                    'payload' => $eventData->payload,
                    'status' => 'received',
                    'received_at' => now(),
                ]
            );

            if ($webhookEvent->wasRecentlyCreated) {
                ProcessWebhookEvent::dispatch($webhookEvent->id);
            }

            return response()->noContent();
        } catch (Throwable $exception) {
            report($exception);
            \Illuminate\Support\Facades\Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => app()->environment('local')
                    ? $exception->getMessage()
                    : 'Webhook validation failed.',
            ], 400);
        }
    }
}
