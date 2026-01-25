<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'order_id',
        'provider',
        'provider_id',
        'provider_invoice_id',
        'invoice_number',
        'number', // Deprecated, use invoice_number
        'status',
        // Customer details
        'customer_name',
        'customer_email',
        'billing_address',
        'customer_vat_number',
        // Financial details
        'amount_due',
        'amount_paid',
        'subtotal',
        'tax_amount',
        'tax_rate',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'hosted_invoice_url',
        'pdf_url',
        'pdf_url_expires_at',
        'invoice_pdf', // Deprecated, use pdf_url
        'payment_failed_email_sent_at',
        'metadata',
    ];

    protected $casts = [
        'amount_due' => 'int',
        'amount_paid' => 'int',
        'subtotal' => 'int',
        'tax_amount' => 'int',
        'tax_rate' => 'decimal:2',
        'billing_address' => 'array',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'pdf_url_expires_at' => 'datetime',
        'payment_failed_email_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Check if the cached PDF URL is still valid.
     */
    public function isPdfCacheValid(): bool
    {
        return $this->pdf_url
            && $this->pdf_url_expires_at
            && $this->pdf_url_expires_at->isFuture();
    }
}
