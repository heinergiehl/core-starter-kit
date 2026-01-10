<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\ProductFactory::new();
    }

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_active',
        'provider',
        'provider_id',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'synced_at' => 'datetime',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }
}
