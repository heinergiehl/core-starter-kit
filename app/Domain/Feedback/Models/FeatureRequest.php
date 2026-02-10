<?php

namespace App\Domain\Feedback\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FeatureRequest extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (FeatureRequest $request): void {
            if ($request->slug) {
                return;
            }

            $request->slug = static::slugCandidate((string) ($request->title ?? ''));
        });
    }

    public static function slugCandidate(string $title, int $attempt = 0): string
    {
        $baseSlug = Str::slug($title) ?: Str::lower(Str::random(8));

        if ($attempt <= 0) {
            return $baseSlug;
        }

        if ($attempt <= 3) {
            return "{$baseSlug}-{$attempt}";
        }

        return "{$baseSlug}-".Str::lower(Str::random(6));
    }

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'status',
        'category',
        'is_public',
        'votes_count',
        'released_at',
    ];

    protected $casts = [
        'is_public' => 'bool',
        'votes_count' => 'int',
        'released_at' => 'datetime',
        'status' => \App\Enums\FeatureStatus::class,
        'category' => \App\Enums\FeatureCategory::class,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeatureVote::class);
    }
}
