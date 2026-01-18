<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_name',
        'description',
        'quantity',
        'unit_price',
        'total_amount',
        'tax_rate',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'int',
        'unit_price' => 'int',
        'total_amount' => 'int',
        'tax_rate' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
