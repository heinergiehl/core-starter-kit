<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\CheckoutStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingStatusController
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sessionUuid = trim((string) $request->query('session', ''));

        if ($sessionUuid !== '') {
            return $this->checkoutStatus($request, $sessionUuid);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json(['status' => 'no_user'], 401);
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

    private function checkoutStatus(Request $request, string $sessionUuid): JsonResponse
    {
        $checkoutSession = $this->checkoutService->findCheckoutSession($sessionUuid);

        if (! $checkoutSession) {
            return $this->processingResponse();
        }

        $user = $request->user();

        if ($user && $user->id !== $checkoutSession->user_id) {
            return $this->processingResponse();
        }

        if (! $user && ! $this->checkoutService->isValidCheckoutSessionSignature(
            $checkoutSession,
            (string) $request->query('sig')
        )) {
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        $owner = $checkoutSession->user;
        if (! $owner) {
            return $this->processingResponse();
        }

        $outcome = $this->checkoutService->resolveSuccessfulCheckoutOutcome($checkoutSession);

        if (! $outcome) {
            return $this->processingResponse();
        }

        if (! $user) {
            if ($checkoutSession->status !== CheckoutStatus::Pending
                || ! $this->checkoutService->markCheckoutSessionCompleted($checkoutSession)
            ) {
                return $this->processingResponse();
            }

            Auth::login($owner);
            $request->session()->regenerate();
        } elseif ($checkoutSession->status === CheckoutStatus::Pending) {
            $this->checkoutService->markCheckoutSessionCompleted($checkoutSession);
        }

        $request->session()->put('checkout_session_uuid', $checkoutSession->uuid);

        return $this->outcomeResponse($outcome);
    }

    private function processingResponse(): JsonResponse
    {
        return response()->json([
            'type' => 'checkout',
            'status' => 'processing',
        ], 202);
    }

    private function outcomeResponse(Subscription|Order $outcome): JsonResponse
    {
        if ($outcome instanceof Subscription) {
            return response()->json([
                'type' => 'subscription',
                'status' => $outcome->status->value,
                'plan_key' => $outcome->plan_key,
                'quantity' => $outcome->quantity,
            ]);
        }

        return response()->json([
            'type' => 'order',
            'status' => $outcome->status->value,
            'plan_key' => $outcome->plan_key,
            'amount' => $outcome->amount,
            'currency' => $outcome->currency,
        ]);
    }
}
