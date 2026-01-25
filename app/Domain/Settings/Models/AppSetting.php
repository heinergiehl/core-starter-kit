<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'type',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'bool',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('app_settings.all');
        });

        static::deleted(function (): void {
            Cache::forget('app_settings.all');
        });
    }
}
