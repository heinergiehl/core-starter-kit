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

            \Illuminate\Support\Facades\Log::info('billing.webhook.received', [
                'provider' => $provider,
                'event_id' => $eventData->id,
                'event_type' => $eventData->type,
            ]);

            // App-first mode: ignore product/price events (return 200 so provider stops retrying)
            if (in_array($eventData->type, self::IGNORED_EVENTS, true)) {
                \Illuminate\Support\Facades\Log::info('billing.webhook.ignored', [
                    'provider' => $provider,
                    'event_id' => $eventData->id,
                    'event_type' => $eventData->type,
                ]);

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

            $shouldDispatch = $webhookEvent->wasRecentlyCreated;

            if (! $shouldDispatch) {
                $status = (string) $webhookEvent->status;
                $lastTouchedAt = $webhookEvent->updated_at ?? $webhookEvent->created_at ?? $webhookEvent->received_at;

                if ($status === 'failed') {
                    $webhookEvent->update([
                        'status' => 'received',
                        'error_message' => null,
                    ]);
                    $shouldDispatch = true;
                } elseif (
                    $status === 'received'
                    && (
                        ! $lastTouchedAt
                        || $lastTouchedAt->lt(now()->subSeconds(max(5, (int) config('saas.billing.webhooks.redispatch_received_after_seconds', 30))))
                    )
                ) {
                    $shouldDispatch = true;
                } elseif (
                    $status === 'processing'
                    && (
                        ! $lastTouchedAt
                        || $lastTouchedAt->lt(now()->subMinutes(max(1, (int) config('saas.billing.webhooks.processing_stale_after_minutes', 15))))
                    )
                ) {
                    $webhookEvent->update([
                        'status' => 'received',
                        'error_message' => null,
                    ]);
                    $shouldDispatch = true;
                }
            }

            if ($shouldDispatch) {
                ProcessWebhookEvent::dispatch($webhookEvent->id);

                \Illuminate\Support\Facades\Log::info('billing.webhook.dispatched', [
                    'provider' => $provider,
                    'event_id' => $eventData->id,
                    'event_type' => $eventData->type,
                    'webhook_event_id' => $webhookEvent->id,
                    'status' => $webhookEvent->status,
                ]);
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
