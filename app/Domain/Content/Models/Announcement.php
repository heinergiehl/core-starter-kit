<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site-wide announcement model.
 *
 * Used for displaying banners across the application.
 */
class Announcement extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Content\AnnouncementFactory::new();
    }

    protected $fillable = [
        'title',
        'message',
        'type',
        'link_text',
        'link_url',
        'is_active',
        'starts_at',
        'ends_at',
        'is_dismissible',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_dismissible' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Scope to active announcements.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Get the current active announcement.
     */
    public static function current(): ?self
    {
        return static::active()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get all currently active announcements.
     */
    public static function allActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get CSS classes for the announcement type.
     */
    public function getTypeClasses(): string
    {
        return match ($this->type) {
            'warning' => 'bg-amber-500/10 border-amber-500/20 text-amber-800 dark:text-amber-200',
            'success' => 'bg-green-500/10 border-green-500/20 text-green-800 dark:text-green-200',
            'danger' => 'bg-red-500/10 border-red-500/20 text-red-800 dark:text-red-200',
            default => 'bg-primary/10 border-primary/20 text-primary-800 dark:text-primary-200',
        };
    }

    /**
     * Get icon for the announcement type.
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
            'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'danger' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        };
    }
}
