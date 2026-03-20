<?php

namespace App\Http\Controllers\Blog;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $categorySlug = $request->input('category');
        $tagSlug = $request->input('tag');

        $posts = BlogPost::query()
            ->published()
            ->with(['category', 'tags', 'author'])
            ->when($search, function (Builder $query, string $search) {
                $this->applySearchFilter($query, $search);
            })
            ->when($categorySlug, function (Builder $query, string $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->when($tagSlug, function (Builder $query, string $tagSlug) {
                $query->whereHas('tags', fn ($q) => $q->where('slug', $tagSlug));
            })
            ->orderByDesc('published_at')
            ->paginate(10)
            ->withQueryString();

        // Get categories and tags for the filter UI (PostgreSQL compatible)
        $categories = BlogCategory::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        $tags = BlogTag::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        // Get active filter models for display
        $activeCategory = $categorySlug ? BlogCategory::where('slug', $categorySlug)->first() : null;
        $activeTag = $tagSlug ? BlogTag::where('slug', $tagSlug)->first() : null;

        return view('blog.index', [
            'posts' => $posts,
            'categories' => $categories,
            'tags' => $tags,
            'search' => $search,
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->published()
            ->with(['category', 'tags', 'author'])
            ->firstOrFail();

        return view('blog.show', [
            'post' => $post,
        ]);
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower(trim($search)).'%';
        $driver = $query->getConnection()->getDriverName();

        $query->where(function (Builder $nestedQuery) use ($driver, $term) {
            if ($driver === 'pgsql') {
                $nestedQuery->whereRaw('title ILIKE ?', [$term])
                    ->orWhereRaw('excerpt ILIKE ?', [$term]);

                return;
            }

            $nestedQuery->whereRaw('LOWER(title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(excerpt) LIKE ?', [$term]);
        });
    }
}
