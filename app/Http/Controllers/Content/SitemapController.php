<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

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
                'solutionSlugs' => SolutionPageController::slugs(),
                'now' => Carbon::now(),
            ])
            ->header('Content-Type', 'application/xml');
    }
}
