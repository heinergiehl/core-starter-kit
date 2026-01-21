<?php

namespace App\Domain\Billing\Adapters\Paddle\Handlers;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Handles Paddle transaction/payment webhook events.
 *
 * Processes: transaction.completed, transaction.paid, transaction.updated, etc.
 * Also syncs invoice and line items data.
 */
class PaddleOrderHandler implements PaddleWebhookHandler
{
    use ResolvesPaddleData;

    public function eventTypes(): array
    {
        return [
            'transaction.completed',
            'transaction.paid',
            'transaction.created',
            'transaction.updated',
            'transaction.billed',
            'transaction.past_due',
            'transaction.payment_failed',
        ];
    }

    public function handle(WebhookEvent $event, array $data): void
    {
        $this->syncOrder($data, $event->type ?? null);
    }

    /**
     * Sync a Paddle order (transaction) to the local database.
     */
    public function syncOrder(array $data, ?string $eventType = null): ?Order
    {
        $orderId = data_get($data, 'id') ?? data_get($data, 'transaction_id');
        $teamId = $this->resolveTeamId($data);

        if (!$orderId || !$teamId) {
            return null;
        }

        $status = (string) (data_get($data, 'status') ?? 'paid');
        $amount = (int) (data_get($data, 'amount')
            ?? data_get($data, 'amount_total')
            ?? data_get($data, 'details.totals.grand_total')
            ?? data_get($data, 'details.totals.total')
            ?? 0);
        $currency = strtoupper((string) (data_get($data, 'currency_code')
            ?? data_get($data, 'currency')
            ?? data_get($data, 'details.totals.currency_code')
            ?? 'USD'));

        $order = Order::query()->updateOrCreate(
            [
                'provider' => 'paddle',
                'provider_id' => (string) $orderId,
            ],
            [
                'team_id' => $teamId,
                'provider_order_id' => (string) $orderId,
                'plan_key' => $this->resolvePlanKey($data),
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => now(),
                'metadata' => Arr::only($data, ['id', 'status', 'items', 'custom_data']),
            ]
        );

        $invoice = $this->syncInvoiceFromOrder($data, $teamId, $order, $status, $amount, $currency);

        $this->maybeNotifyPaymentFailed($order, $invoice, $status, $eventType, $data);

        $this->recordDiscountRedemption(
            $data,
            $teamId,
            $order->plan_key,
            data_get($data, 'custom_data.price_key'),
            (string) $orderId
        );

        return $order;
    }

    /**
     * Sync invoice from order data.
     */
    private function syncInvoiceFromOrder(
        array $data,
        int $teamId,
        Order $order,
        string $status,
        int $amount,
        string $currency
    ): Invoice {
        $normalizedStatus = strtolower($status);
        $paid = in_array($normalizedStatus, ['paid', 'completed', 'settled'], true);

        // Extract invoice identifiers
        $transactionId = data_get($data, 'id');
        $invoiceNumber = data_get($data, 'invoice_number');
        $invoiceId = data_get($data, 'invoice_id');

        // Extract customer information
        $customer = data_get($data, 'customer', []);
        $address = data_get($data, 'address', []) ?? data_get($customer, 'address', []);
        $billingDetails = data_get($data, 'billing_details', []);

        // Extract tax breakdown
        $details = data_get($data, 'details', []);
        $totals = data_get($details, 'totals', []);
        $subtotal = (int) data_get($totals, 'subtotal', 0);
        $taxAmount = (int) data_get($totals, 'tax', 0);

        // PDF URLs
        $invoiceUrl = data_get($billingDetails, 'invoice_url')
            ?? data_get($data, 'invoice_url')
            ?? data_get($data, 'checkout.invoice_url');

        $invoicePdf = data_get($billingDetails, 'invoice_pdf')
            ?? data_get($data, 'invoice_pdf')
            ?? data_get($data, 'checkout.invoice_pdf');

        $invoice = Invoice::query()->updateOrCreate(
            [
                'provider' => 'paddle',
                'provider_id' => (string) $transactionId,
            ],
            [
                'team_id' => $teamId,
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'provider_invoice_id' => $invoiceId,
                'status' => $normalizedStatus,

                // Customer information
                'customer_name' => data_get($customer, 'name'),
                'customer_email' => data_get($customer, 'email'),
                'billing_address' => [
                    'line1' => data_get($address, 'first_line'),
                    'line2' => data_get($address, 'second_line'),
                    'city' => data_get($address, 'city'),
                    'postal_code' => data_get($address, 'postal_code'),
                    'country' => data_get($address, 'country_code'),
                ],
                'customer_vat_number' => data_get($billingDetails, 'tax_identifier'),

                // Financial breakdown
                'amount_due' => $amount,
                'amount_paid' => $paid ? $amount : 0,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $this->calculateTaxRate($subtotal, $taxAmount),
                'currency' => $currency,

                // Timestamps
                'issued_at' => $this->timestampToDateTime(data_get($data, 'created_at') ?? data_get($data, 'billed_at')),
                'paid_at' => $paid ? now() : null,

                // PDF URLs
                'hosted_invoice_url' => $invoiceUrl,
                'pdf_url' => $invoicePdf,
                'pdf_url_expires_at' => null,

                'metadata' => Arr::only($data, [
                    'id', 'status', 'items', 'custom_data',
                    'invoice_number', 'invoice_id', 'billing_details',
                    'customer', 'address', 'details'
                ]),
            ]
        );

        // Sync line items
        $this->syncInvoiceLineItems($invoice, data_get($data, 'items', []));

        return $invoice;
    }

    private function maybeNotifyPaymentFailed(
        Order $order,
        Invoice $invoice,
        string $status,
        ?string $eventType,
        array $data
    ): void {
        if ($invoice->payment_failed_email_sent_at) {
            return;
        }

        $normalizedStatus = strtolower($status);
        $failedEvent = $eventType && in_array($eventType, ['transaction.past_due', 'transaction.payment_failed'], true);
        $failedStatus = in_array($normalizedStatus, ['past_due', 'payment_failed', 'failed'], true);

        if (!$failedEvent && !$failedStatus) {
            return;
        }

        $team = $order->team;
        $owner = $team?->owner;

        if (!$owner) {
            return;
        }

        $failureReason = data_get($data, 'failure_reason')
            ?? data_get($data, 'error')
            ?? data_get($data, 'details.failure_reason')
            ?? null;

        $owner->notify(new PaymentFailedNotification(
            planName: $this->resolvePlanName($order->plan_key),
            amount: $order->amount,
            currency: $order->currency,
            failureReason: is_string($failureReason) ? $failureReason : null,
        ));

        $invoice->forceFill(['payment_failed_email_sent_at' => now()])->save();
    }

    /**
     * Calculate tax rate from subtotal and tax amount.
     */
    private function calculateTaxRate(int $subtotal, int $taxAmount): ?float
    {
        if ($subtotal > 0 && $taxAmount > 0) {
            return round(($taxAmount / $subtotal) * 100, 2);
        }

        return null;
    }

    /**
     * Sync invoice line items.
     */
    private function syncInvoiceLineItems(Invoice $invoice, array $items): void
    {
        $invoice->lineItems()->delete();

        foreach ($items as $item) {
            $priceData = data_get($item, 'price', []);
            $productData = data_get($item, 'product', []);
            $itemTotals = data_get($item, 'totals', []);
            $billingPeriod = data_get($item, 'billing_period', []);

            $invoice->lineItems()->create([
                'product_name' => data_get($productData, 'name')
                    ?? data_get($priceData, 'description')
                    ?? 'Product',
                'description' => data_get($priceData, 'name')
                    ?? data_get($priceData, 'description'),
                'quantity' => (int) data_get($item, 'quantity', 1),
                'unit_price' => (int) data_get($priceData, 'unit_price.amount', 0),
                'total_amount' => (int) data_get($itemTotals, 'total', 0),
                'tax_rate' => null,
                'period_start' => data_get($billingPeriod, 'starts_at')
                    ? Carbon::parse(data_get($billingPeriod, 'starts_at'))->toDateString()
                    : null,
                'period_end' => data_get($billingPeriod, 'ends_at')
                    ? Carbon::parse(data_get($billingPeriod, 'ends_at'))->toDateString()
                    : null,
                'metadata' => $item,
            ]);
        }
    }

    /**
     * Record discount redemption from webhook data.
     */
    private function recordDiscountRedemption(
        array $data,
        int $teamId,
        ?string $planKey,
        ?string $priceKey,
        string $providerId
    ): void {
        $customData = data_get($data, 'custom_data', []);
        $discountId = $customData['discount_id'] ?? data_get($data, 'metadata.discount_id');
        $discountCode = $customData['discount_code'] ?? data_get($data, 'metadata.discount_code');

        if (!$discountId && !$discountCode) {
            return;
        }

        $discount = null;

        if ($discountId) {
            $discount = Discount::query()->find($discountId);
        } elseif ($discountCode) {
            $discount = Discount::query()
                ->where('provider', 'paddle')
                ->where('code', strtoupper((string) $discountCode))
                ->first();
        }

        if (!$discount) {
            return;
        }

        $team = Team::find($teamId);

        if (!$team) {
            return;
        }

        $userId = $customData['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        app(DiscountService::class)->recordRedemption(
            $discount,
            $team,
            $user,
            'paddle',
            $providerId,
            $planKey,
            $priceKey,
            [
                'source' => 'paddle_webhook',
            ]
        );
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (!$planKey) {
            return 'subscription';
        }

        try {
            $plan = app(\App\Domain\Billing\Services\BillingPlanService::class)->plan($planKey);

            return $plan['name'] ?? ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }
}
