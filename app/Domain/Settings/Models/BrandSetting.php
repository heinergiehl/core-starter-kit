<?php

namespace App\Domain\Settings\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'app_name',
        'logo_path',
        'template',
        'color_primary',
        'color_secondary',
        'color_accent',
        'color_bg',
        'color_fg',
        'invoice_name',
        'invoice_email',
        'invoice_address',
        'invoice_tax_id',
        'invoice_footer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
