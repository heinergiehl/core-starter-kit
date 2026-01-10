<?php

namespace App\Http\Controllers\Blog;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController
{
    public function index(): View
    {
        $posts = BlogPost::query()
            ->where('is_published', true)
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('blog.index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with(['category', 'tags'])
            ->firstOrFail();

        return view('blog.show', [
            'post' => $post,
        ]);
    }
}
