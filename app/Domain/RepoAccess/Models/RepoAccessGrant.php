<?php

namespace App\Domain\RepoAccess\Models;

use App\Enums\RepoAccessGrantStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepoAccessGrant extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'repository_owner',
        'repository_name',
        'github_username',
        'status',
        'last_error',
        'last_attempted_at',
        'invited_at',
        'granted_at',
        'metadata',
    ];

    protected $casts = [
        'status' => RepoAccessGrantStatus::class,
        'last_attempted_at' => 'datetime',
        'invited_at' => 'datetime',
        'granted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
