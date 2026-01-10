<?php

namespace App\Domain\Feedback\Models;

use App\Domain\Organization\Models\Team;
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

            while (static::query()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $request->slug = $slug;
        });
    }

    protected $fillable = [
        'user_id',
        'team_id',
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
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeatureVote::class);
    }
}
