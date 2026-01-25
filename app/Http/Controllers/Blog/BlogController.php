<?php

namespace App\Http\Controllers\Blog;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
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
            // Search filter (ILIKE for case-insensitive PostgreSQL search)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('title ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('excerpt ILIKE ?', ["%{$search}%"]);
                });
            })
            // Category filter
            ->when($categorySlug, function ($query, $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            // Tag filter
            ->when($tagSlug, function ($query, $tagSlug) {
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

    public function show(string $slug): View
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
}
