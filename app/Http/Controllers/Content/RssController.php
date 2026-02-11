<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class RssController
{
    public function __invoke(): Response
    {
        $cacheSeconds = max((int) config('saas.seo.rss_cache_seconds', 900), 0);
        $cacheHeader = $cacheSeconds > 0
            ? "public, max-age={$cacheSeconds}, stale-while-revalidate=3600"
            : 'no-cache, no-store, must-revalidate';

        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->take(20)
            ->get(['slug', 'title', 'excerpt', 'published_at', 'updated_at']);

        $cacheKey = 'seo:rss:v1:'.sha1(json_encode([
            'locale' => app()->getLocale(),
            'posts' => $posts->map(fn (BlogPost $post): array => [
                'slug' => $post->slug,
                'updated_at' => optional($post->updated_at)->toAtomString(),
                'published_at' => optional($post->published_at)->toAtomString(),
            ])->all(),
        ]) ?: '');

        $payload = [
            'posts' => $posts,
            'updatedAt' => $posts->first()?->updated_at ?? now(),
        ];

        $xml = (! app()->environment('testing') && $cacheSeconds > 0)
            ? Cache::remember($cacheKey, $cacheSeconds, fn (): string => view('rss', $payload)->render())
            : view('rss', $payload)->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', $cacheHeader)
            ->header('X-Robots-Tag', 'noindex, follow');
    }
}
