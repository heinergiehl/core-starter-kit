<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'subscription_id',
        'order_id',
        'provider',
        'provider_id',
        'number',
        'status',
        'amount_due',
        'amount_paid',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'hosted_invoice_url',
        'invoice_pdf',
        'metadata',
    ];

    protected $casts = [
        'amount_due' => 'int',
        'amount_paid' => 'int',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
