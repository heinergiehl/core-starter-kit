<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BillingStatusController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;
        $sessionUuid = trim((string) $request->query('session', ''));

        if (!$team) {
            return response()->json(['status' => 'no_team'], 404);
        }

        // If session UUID provided, try to find subscription for that checkout session
        if ($sessionUuid !== '') {
            $checkoutSession = \App\Domain\Billing\Models\CheckoutSession::where('uuid', $sessionUuid)->first();
            
            if ($checkoutSession && $checkoutSession->team_id === $team->id) {
                $subscription = Subscription::query()
                    ->where('team_id', $team->id)
                    ->where('created_at', '>=', $checkoutSession->created_at->subMinutes(5))
                    ->latest('id')
                    ->first();

                if ($subscription) {
                    return response()->json([
                        'type' => 'subscription',
                        'status' => $subscription->status,
                        'plan_key' => $subscription->plan_key,
                        'quantity' => $subscription->quantity,
                    ]);
                }
                
                $order = Order::query()
                    ->where('team_id', $team->id)
                    ->where('created_at', '>=', $checkoutSession->created_at->subMinutes(5))
                    ->latest('id')
                    ->first();

                if ($order) {
                    return response()->json([
                        'type' => 'order',
                        'status' => $order->status,
                        'plan_key' => $order->plan_key,
                        'amount' => $order->amount,
                        'currency' => $order->currency,
                    ]);
                }
            }
        }

        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('created_at')
            ->first();

        if (!$subscription) {
            $subscription = Subscription::query()
                ->where('team_id', $team->id)
                ->latest('created_at')
                ->first();
        }

        if (!$subscription) {
            $order = Order::query()
                ->where('team_id', $team->id)
                ->latest('created_at')
                ->first();

            if (!$order) {
                return response()->json(['status' => 'no_subscription'], 404);
            }

            return response()->json([
                'type' => 'order',
                'status' => $order->status,
                'plan_key' => $order->plan_key,
                'amount' => $order->amount,
                'currency' => $order->currency,
            ]);
        }

        return response()->json([
            'type' => 'subscription',
            'status' => $subscription->status,
            'plan_key' => $subscription->plan_key,
            'quantity' => $subscription->quantity,
        ]);
    }
}
