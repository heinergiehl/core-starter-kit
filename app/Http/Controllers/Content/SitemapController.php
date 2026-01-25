<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Response;

class SitemapController
{
    public function __invoke(): Response
    {
        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at']);

        return response()
            ->view('sitemap', [
                'posts' => $posts,
            ])
            ->header('Content-Type', 'application/xml');
    }
}
