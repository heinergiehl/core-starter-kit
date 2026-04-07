<?php

namespace App\Domain\Feedback\Models;

use App\Models\User;
use Database\Factories\Domain\Feedback\Models\SurveyResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    /** @use HasFactory<SurveyResponseFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'survey_id',
        'user_id',
        'answers',
        'score',
        'max_score',
        'score_percent',
        'submitted_at',
        'locale',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'int',
        'max_score' => 'int',
        'score_percent' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    protected static function newFactory(): SurveyResponseFactory
    {
        return SurveyResponseFactory::new();
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
