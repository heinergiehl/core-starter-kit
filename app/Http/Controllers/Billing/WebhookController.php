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
    public function __invoke(Request $request, string $provider): Response
    {
        try {
            $adapter = app(BillingProviderManager::class)->adapter($provider);
            $eventData = $adapter->parseWebhook($request);

            $webhookEvent = WebhookEvent::query()->firstOrCreate(
                [
                    'provider' => $provider,
                    'event_id' => $eventData['id'],
                ],
                [
                    'type' => $eventData['type'],
                    'payload' => $eventData['payload'],
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

            return response()->json([
                'error' => app()->environment('local')
                    ? $exception->getMessage()
                    : 'Webhook validation failed.',
            ], 400);
        }
    }
}
