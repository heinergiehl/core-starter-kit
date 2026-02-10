<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingStatusController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionUuid = trim((string) $request->query('session', ''));

        if (! $user) {
            return response()->json(['status' => 'no_user'], 401);
        }

        // When a checkout session UUID is provided, only report records that can belong
        // to that checkout. This avoids false failures from stale subscriptions/orders.
        if ($sessionUuid !== '') {
            $checkoutSession = CheckoutSession::query()
                ->where('uuid', $sessionUuid)
                ->where('user_id', $user->id)
                ->first();

            if (! $checkoutSession) {
                return response()->json([
                    'type' => 'checkout',
                    'status' => 'processing',
                ], 202);
            }

            $windowStart = $checkoutSession->created_at->copy()->subSeconds(30);

            $subscription = Subscription::query()
                ->where('user_id', $user->id)
                ->where('plan_key', $checkoutSession->plan_key)
                ->where('created_at', '>=', $windowStart)
                ->latest('id')
                ->first();

            if ($subscription) {
                return response()->json([
                    'type' => 'subscription',
                    'status' => $subscription->status->value,
                    'plan_key' => $subscription->plan_key,
                    'quantity' => $subscription->quantity,
                ]);
            }

            $order = Order::query()
                ->where('user_id', $user->id)
                ->where('plan_key', $checkoutSession->plan_key)
                ->where('created_at', '>=', $windowStart)
                ->latest('id')
                ->first();

            if ($order) {
                return response()->json([
                    'type' => 'order',
                    'status' => $order->status->value,
                    'plan_key' => $order->plan_key,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                ]);
            }

            return response()->json([
                'type' => 'checkout',
                'status' => 'processing',
            ], 202);
        }

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->latest('created_at')
            ->first();

        if (! $subscription) {
            $subscription = Subscription::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->first();
        }

        if (! $subscription) {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->first();

            if (! $order) {
                return response()->json(['status' => 'no_subscription'], 404);
            }

            return response()->json([
                'type' => 'order',
                'status' => $order->status->value,
                'plan_key' => $order->plan_key,
                'amount' => $order->amount,
                'currency' => $order->currency,
            ]);
        }

        return response()->json([
            'type' => 'subscription',
            'status' => $subscription->status->value,
            'plan_key' => $subscription->plan_key,
            'quantity' => $subscription->quantity,
        ]);
    }
}
