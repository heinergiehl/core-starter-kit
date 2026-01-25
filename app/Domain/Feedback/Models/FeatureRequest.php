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

            $baseSlug = Str::slug($request->title ?? '') ?: Str::random(8);
            $slug = $baseSlug;
            $counter = 1;
            // Prevent infinite loops in high concurrency
            $maxAttempts = 10;

            while (static::query()->where('slug', $slug)->exists()) {
                if ($counter > $maxAttempts) {
                    $slug = "{$baseSlug}-".Str::random(6);
                    break;
                }
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $request->slug = $slug;
        });
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
