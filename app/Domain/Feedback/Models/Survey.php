<?php

namespace App\Domain\Feedback\Models;

use App\Enums\SurveyStatus;
use App\Models\User;
use Database\Factories\Domain\Feedback\Models\SurveyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Survey extends Model
{
    /** @use HasFactory<SurveyFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'is_public',
        'requires_auth',
        'allow_multiple_submissions',
        'submit_label',
        'success_title',
        'success_message',
        'starts_at',
        'ends_at',
        'questions',
    ];

    protected $casts = [
        'status' => SurveyStatus::class,
        'is_public' => 'bool',
        'requires_auth' => 'bool',
        'allow_multiple_submissions' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'questions' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $survey): void {
            if (blank($survey->slug)) {
                $survey->slug = Str::slug((string) $survey->title) ?: Str::lower(Str::random(8));
            }
        });
    }

    protected static function newFactory(): SurveyFactory
    {
        return SurveyFactory::new();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function acceptsResponsesAt(?\Illuminate\Support\Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->status !== SurveyStatus::Published || ! $this->is_public) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($at)) {
            return false;
        }

        return true;
    }

    public function hasUserResponse(User $user): bool
    {
        return $this->responses()
            ->where('user_id', $user->id)
            ->exists();
    }
}
