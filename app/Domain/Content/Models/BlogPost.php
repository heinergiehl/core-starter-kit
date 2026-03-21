<?php

namespace App\Domain\Content\Models;

use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'translation_group_uuid',
        'locale',
        'content_source',
        'content_source_key',
        'content_source_path',
        'content_source_hash',
        'content_source_synced_at',
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
        'content_source_synced_at' => 'datetime',
        'reading_time' => 'int',
        'status' => \App\Enums\PostStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogPost $post): void {
            if (blank($post->translation_group_uuid)) {
                $post->translation_group_uuid = (string) Str::uuid();
            }

            if (blank($post->locale)) {
                $post->locale = (string) config('saas.locales.default', config('app.locale', 'en'));
            }
        });

        static::saving(function (BlogPost $post) {
            if (blank($post->slug) && filled($post->title)) {
                $post->slug = Str::slug($post->title);
            }

            $status = $post->status;

            if (is_string($status)) {
                $status = PostStatus::tryFrom($status);
            }

            if ($status === PostStatus::Published && empty($post->published_at)) {
                $post->published_at = now();
            }

            if (filled($post->body_html)) {
                $wordCount = str_word_count(strip_tags($post->body_html));
                $post->reading_time = max(1, ceil($wordCount / 200));
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', \App\Enums\PostStatus::Published)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeForLocale(Builder $query, ?string $locale): Builder
    {
        $targetLocale = filled($locale)
            ? (string) $locale
            : (string) config('saas.locales.default', config('app.locale', 'en'));

        return $query->where('locale', $targetLocale);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', \App\Enums\PostStatus::Draft);
    }

    public function getSeoTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function getSeoDescriptionAttribute(): string
    {
        return $this->meta_description
            ?: $this->excerpt
            ?: Str::limit(strip_tags($this->body_html ?: (string) Str::markdown($this->body_markdown ?? '')), 160);
    }

    public function getLocaleLabelAttribute(): string
    {
        return \App\Enums\Locale::tryFrom((string) $this->locale)?->getLabel() ?? strtoupper((string) $this->locale);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'translation_group_uuid', 'translation_group_uuid')
            ->orderBy('locale');
    }

    public function translationForLocale(string $locale): ?self
    {
        if ($this->relationLoaded('translations')) {
            /** @var Collection<int, self> $translations */
            $translations = $this->getRelation('translations');

            return $translations->firstWhere('locale', $locale);
        }

        return $this->translations()
            ->where('locale', $locale)
            ->first();
    }

    /**
     * @return Collection<int, string>
     */
    public function availableTranslationLocales(): Collection
    {
        if ($this->relationLoaded('translations')) {
            /** @var Collection<int, self> $translations */
            $translations = $this->getRelation('translations');

            return $translations
                ->pluck('locale')
                ->filter()
                ->values();
        }

        return $this->translations()
            ->pluck('locale');
    }

    /**
     * @return array<int, string>
     */
    public function missingTranslationLocales(): array
    {
        $supportedLocales = array_keys(config('saas.locales.supported', ['en' => 'English']));

        return array_values(array_diff($supportedLocales, $this->availableTranslationLocales()->all()));
    }

    public function translationStatusSummary(): string
    {
        $translations = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->get();

        return $translations
            ->map(function (BlogPost $translation): string {
                $locale = strtoupper((string) $translation->locale);
                $status = $translation->status instanceof PostStatus
                    ? $translation->status->value
                    : (string) $translation->status;

                return "{$locale} {$status}";
            })
            ->implode(' | ');
    }

    public function isManagedByMarkdown(): bool
    {
        return $this->content_source === 'markdown' && filled($this->content_source_path);
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
