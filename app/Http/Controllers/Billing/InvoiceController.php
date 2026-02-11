<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController
{
    /**
     * Download invoice PDF for an order.
     */
    public function download(Request $request, int $order): Response|StreamedResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $invoice = Invoice::query()
            ->where('user_id', $user->id)
            ->where('order_id', $order)
            ->firstOrFail();

        return $this->downloadPdf($invoice);
    }

    /**
     * Download invoice PDF directly by invoice ID.
     */
    public function downloadInvoice(Request $request, int $invoice): Response|StreamedResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $invoiceModel = Invoice::query()
            ->where('id', $invoice)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return $this->downloadPdf($invoiceModel);
    }

    /**
     * Download PDF for an invoice.
     */
    private function downloadPdf(Invoice $invoice): Response|StreamedResponse|RedirectResponse
    {
        // Check if we have a valid cached PDF URL
        if ($invoice->isPdfCacheValid() && $invoice->pdf_url && $this->isAllowedInvoiceUrl($invoice->pdf_url, $invoice->provider)) {
            return $this->streamFromUrl($invoice->pdf_url, $invoice);
        }

        // If we have a hosted invoice URL, redirect to it
        if ($invoice->hosted_invoice_url && $this->isAllowedInvoiceUrl($invoice->hosted_invoice_url, $invoice->provider)) {
            return redirect()->away($invoice->hosted_invoice_url);
        }

        // Fallback: fetch fresh PDF URL from provider API
        $pdfUrl = $this->fetchPdfUrlFromProvider($invoice);

        if ($pdfUrl) {
            // Cache the PDF URL (Paddle URLs typically valid for 24 hours)
            $invoice->update([
                'pdf_url' => $pdfUrl,
                'pdf_url_expires_at' => now()->addHours(23), // Cache for 23 hours
            ]);

            if ($this->isAllowedInvoiceUrl($pdfUrl, $invoice->provider)) {
                return $this->streamFromUrl($pdfUrl, $invoice);
            }
        }

        return redirect()->route('billing.index')
            ->with('error', __('Invoice PDF is currently unavailable from the provider. Please try again later.'));
    }

    /**
     * Stream PDF from URL.
     */
    private function streamFromUrl(string $url, Invoice $invoice): StreamedResponse
    {
        $number = $invoice->invoice_number ?: $invoice->id;
        $filename = "invoice-{$number}.pdf";

        return response()->streamDownload(function () use ($url) {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withOptions(['stream' => true])
                ->get($url);

            if (! $response->successful()) {
                abort(404, 'Failed to fetch invoice PDF.');
            }

            $stream = $response->toPsrResponse()->getBody();

            while (! $stream->eof()) {
                echo $stream->read(65536);
            }
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function isAllowedInvoiceUrl(string $url, string $provider): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        $allowlist = match ($provider) {
            'paddle' => ['paddle.com', 'paddlecdn.com'],
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

    /**
     * Fetch PDF URL from billing provider API.
     */
    private function fetchPdfUrlFromProvider(Invoice $invoice): ?string
    {
        $provider = $invoice->provider;

        if ($provider === 'paddle') {
            return $this->fetchPaddleInvoicePdf($invoice);
        }

        if ($provider === 'stripe') {
            return $this->fetchStripeInvoicePdf($invoice);
        }

        return null;
    }

    /**
     * Fetch Paddle invoice/transaction PDF URL.
     */
    private function fetchPaddleInvoicePdf(Invoice $invoice): ?string
    {
        // Use provider_id which contains the transaction ID (txn_xxx)
        // NOT provider_invoice_id which contains the invoice ID (inv_xxx)
        $transactionId = $invoice->provider_id;

        if (! $transactionId) {
            return null;
        }

        $apiKey = trim((string) config('services.paddle.api_key', ''));

        if ($apiKey === '') {
            Log::warning('Skipped Paddle invoice fetch because PADDLE_API_KEY is missing.', [
                'invoice_id' => $invoice->id,
            ]);

            return null;
        }

        $baseUrl = config('services.paddle.environment') === 'sandbox'
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';

        try {
            $response = Http::withToken($apiKey)
                ->get("{$baseUrl}/transactions/{$transactionId}/invoice");

            if ($response->successful()) {
                return data_get($response->json(), 'data.url');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Fetch Stripe invoice PDF URL.
     */
    private function fetchStripeInvoicePdf(Invoice $invoice): ?string
    {
        $invoiceId = $invoice->provider_invoice_id;

        if (! $invoiceId) {
            return null;
        }

        $secret = trim((string) config('services.stripe.secret', ''));

        if ($secret === '') {
            Log::warning('Skipped Stripe invoice fetch because STRIPE_SECRET is missing.', [
                'invoice_id' => $invoice->id,
            ]);

            return null;
        }

        try {
            $stripe = new \Stripe\StripeClient($secret);
            $stripeInvoice = $stripe->invoices->retrieve($invoiceId);

            return $stripeInvoice->invoice_pdf ?? $stripeInvoice->hosted_invoice_url;
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
