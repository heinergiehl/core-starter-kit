<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BrandSetting extends Model
{
    use HasFactory;

    public const GLOBAL_ID = 1;

    protected $fillable = [
        'app_name',
        'logo_path',
        'template',
        'invoice_name',
        'invoice_email',
        'invoice_address',
        'invoice_tax_id',
        'invoice_footer',
        'email_primary_color',
        'email_secondary_color',
    ];

    protected static function booted(): void
    {
        $flush = function (BrandSetting $setting): void {
            Cache::forget('branding.global');
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
