<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }
        $hasSubscription = $user->hasActiveSubscription();

        $hasOrder = \App\Domain\Billing\Models\Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                \App\Enums\OrderStatus::Paid->value,
                \App\Enums\OrderStatus::Completed->value,
            ])
            ->exists();

        if (! $hasSubscription && ! $hasOrder) {
            return redirect()
                ->route('pricing')
                ->with('warning', __('Please complete your subscription to access the app.'));
        }

        return $next($request);
    }
}
