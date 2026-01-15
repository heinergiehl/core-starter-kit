<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderDeletionOutbox extends Model
{
    protected $table = 'provider_deletion_outbox';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider',
        'entity_type',
        'provider_id',
        'status',
        'attempts',
        'last_error',
        'completed_at',
    ];

    protected $casts = [
        'attempts' => 'int',
        'completed_at' => 'datetime',
    ];
}
