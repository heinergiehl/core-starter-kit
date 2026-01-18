<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController
{
    /**
     * Download invoice PDF for an order.
     */
    public function download(Request $request, int $order): Response|StreamedResponse|RedirectResponse
    {
        $team = $request->user()?->currentTeam;
        
        if (!$team) {
            abort(403, 'No team selected.');
        }

        $invoice = Invoice::query()
            ->where('team_id', $team->id)
            ->where('order_id', $order)
            ->firstOrFail();

        return $this->downloadPdf($invoice);
    }

    /**
     * Download invoice PDF directly by invoice ID.
     */
    public function downloadInvoice(Request $request, int $invoice): Response|StreamedResponse|RedirectResponse
    {
        $team = $request->user()?->currentTeam;
        
        if (!$team) {
            abort(403, 'No team selected.');
        }

        $invoiceModel = Invoice::query()
            ->where('id', $invoice)
            ->where('team_id', $team->id)
            ->firstOrFail();

        return $this->downloadPdf($invoiceModel);
    }

    /**
     * Download PDF for an invoice.
     */
    private function downloadPdf(Invoice $invoice): Response|StreamedResponse|RedirectResponse
    {
        // Check if we have a valid cached PDF URL
        if ($invoice->isPdfCacheValid()) {
            return $this->streamFromUrl($invoice->pdf_url, $invoice);
        }

        // If we have a hosted invoice URL, redirect to it
        if ($invoice->hosted_invoice_url) {
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
            
            return $this->streamFromUrl($pdfUrl, $invoice);
        }

        return redirect()->route('billing.index')
            ->with('error', __('Invoice PDF is currently unavailable from the provider. Please try again later.'));
    }

    /**
     * Stream PDF from URL.
     */
    private function streamFromUrl(string $url, Invoice $invoice): StreamedResponse
    {
        $filename = "invoice-{$invoice->invoice_number}.pdf";

        return response()->streamDownload(function () use ($url) {
            $response = Http::get($url);
            
            if ($response->successful()) {
                echo $response->body();
            } else {
                abort(404, 'Failed to fetch invoice PDF.');
            }
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
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
        
        if (!$transactionId) {
            return null;
        }

        $apiKey = config('services.paddle.api_key');
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
        
        if (!$invoiceId) {
            return null;
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $stripeInvoice = $stripe->invoices->retrieve($invoiceId);
            
            return $stripeInvoice->invoice_pdf ?? $stripeInvoice->hosted_invoice_url;
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
