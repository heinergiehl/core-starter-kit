<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController
{
    public function __invoke(): Response
    {
        $cacheSeconds = max((int) config('saas.seo.sitemap_cache_seconds', 3600), 0);
        $cacheHeader = $cacheSeconds > 0
            ? "public, max-age={$cacheSeconds}, stale-while-revalidate=86400"
            : 'no-cache, no-store, must-revalidate';

        $now = Carbon::now();
        $latestBlogPost = BlogPost::published()
            ->orderByDesc('updated_at')
            ->orderByDesc('published_at')
            ->first(['slug', 'updated_at', 'published_at']);
        $blogEnabled = (bool) config('saas.features.blog', true);

        $entries = [
            [
                'loc' => route('sitemap.marketing'),
                'lastmod' => $now->toAtomString(),
            ],
        ];

        if ($blogEnabled) {
            $entries[] = [
                'loc' => route('sitemap.blog'),
                'lastmod' => optional($latestBlogPost?->updated_at ?? $latestBlogPost?->published_at ?? $now)->toAtomString(),
            ];
        }

        $cacheKey = 'seo:sitemap:index:v1:'.sha1(json_encode([
            'blog_enabled' => $blogEnabled,
            'blog_slug' => $latestBlogPost?->slug,
            'blog_updated' => optional($latestBlogPost?->updated_at)?->toAtomString(),
            'blog_published' => optional($latestBlogPost?->published_at)?->toAtomString(),
        ]) ?: '');

        $xml = (! app()->environment('testing') && $cacheSeconds > 0)
            ? Cache::remember($cacheKey, $cacheSeconds, fn (): string => view('sitemap-index', ['entries' => $entries])->render())
            : view('sitemap-index', ['entries' => $entries])->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', $cacheHeader)
            ->header('X-Robots-Tag', 'noindex, follow');
    }
}
