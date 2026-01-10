<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCustomer extends Model
{
    use HasFactory;

    protected $table = 'billing_customers';

    protected $fillable = [
        'team_id',
        'provider',
        'provider_id',
        'email',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
