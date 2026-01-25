<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Response;

class RssController
{
    public function __invoke(): Response
    {
        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->take(20)
            ->get(['slug', 'title', 'excerpt', 'published_at', 'updated_at']);

        return response()
            ->view('rss', [
                'posts' => $posts,
                'updatedAt' => $posts->first()?->updated_at ?? now(),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
