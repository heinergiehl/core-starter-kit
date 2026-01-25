<?php

namespace App\Domain\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body_markdown',
        'body_html',
        'featured_image',
        'meta_title',
        'meta_description',
        'reading_time',
        'published_at',
        'status',
        'author_id',
        'category_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'reading_time' => 'int',
        'status' => \App\Enums\PostStatus::class,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function (BlogPost $post) {
            // Auto-generate slug from title if not set
            if (empty($post->slug) && ! empty($post->title)) {
                $post->slug = Str::slug($post->title);
            }

            // Calculate reading time from content
            if (! empty($post->body_html)) {
                $wordCount = str_word_count(strip_tags($post->body_html));
                $post->reading_time = max(1, ceil($wordCount / 200));
            }
        });
    }

    /**
     * Scope to published posts.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', \App\Enums\PostStatus::Published)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope to draft posts.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', \App\Enums\PostStatus::Draft);
    }

    /**
     * Get the SEO title.
     */
    public function getSeoTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }

    /**
     * Get the SEO description.
     */
    public function getSeoDescriptionAttribute(): string
    {
        return $this->meta_description ?: $this->excerpt ?: Str::limit(strip_tags($this->body_html), 160);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }
}
