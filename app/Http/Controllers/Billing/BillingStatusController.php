<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Contracts\BillingOwnerResolver as BillingOwnerResolverContract;
use App\Domain\Billing\Data\BillingOwner;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingStatusController
{
    public function __construct(
        private readonly BillingOwnerResolverContract $billingOwnerResolver,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionUuid = trim((string) $request->query('session', ''));

        if (! $user) {
            return response()->json(['status' => 'no_user'], 401);
        }

        $billingOwner = $this->billingOwnerResolver->forUser($user) ?? BillingOwner::forUser($user);

        // When a checkout session UUID is provided, only report records that can belong
        // to that checkout. This avoids false failures from stale subscriptions/orders.
        if ($sessionUuid !== '') {
            $checkoutSession = $billingOwner->applyToQuery(CheckoutSession::query())
                ->where('uuid', $sessionUuid)
                ->first();

            if (! $checkoutSession) {
                return response()->json([
                    'type' => 'checkout',
                    'status' => 'processing',
                ], 202);
            }

            $windowStart = $checkoutSession->created_at->copy()->subSeconds(30);

            $subscription = $billingOwner->applyToQuery(Subscription::query())
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

            $order = $billingOwner->applyToQuery(Order::query())
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

        $subscription = $billingOwner->applyToQuery(Subscription::query())
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->latest('created_at')
            ->first();

        if (! $subscription) {
            $subscription = $billingOwner->applyToQuery(Subscription::query())
                ->latest('created_at')
                ->first();
        }

        if (! $subscription) {
            $order = $billingOwner->applyToQuery(Order::query())
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
