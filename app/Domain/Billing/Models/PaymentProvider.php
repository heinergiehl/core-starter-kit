<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'configuration',
        'connection_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'configuration' => 'encrypted:array', // Auto-encrypts/decrypts
        'connection_settings' => 'array',
    ];
}
