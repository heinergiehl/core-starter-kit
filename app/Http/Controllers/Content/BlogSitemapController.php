<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Models\BlogPost;
use App\Support\Localization\LocalizedRouteService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class BlogSitemapController
{
    public function __construct(
        private readonly LocalizedRouteService $localizedRouteService
    ) {}

    public function __invoke(): Response
    {
        $cacheSeconds = max((int) config('saas.seo.sitemap_cache_seconds', 3600), 0);
        $cacheHeader = $cacheSeconds > 0
            ? "public, max-age={$cacheSeconds}, stale-while-revalidate=86400"
            : 'no-cache, no-store, must-revalidate';

        $supportedLocales = $this->localizedRouteService->supportedLocales();
        $defaultLocale = $this->localizedRouteService->defaultLocale();
        $blogEnabled = (bool) config('saas.features.blog', true);
        $latestBlogPost = $blogEnabled
            ? BlogPost::published()
                ->orderByDesc('updated_at')
                ->orderByDesc('published_at')
                ->first(['updated_at', 'published_at'])
            : null;

        $posts = $blogEnabled
            ? BlogPost::published()
                ->orderByDesc('published_at')
                ->get(['translation_group_uuid', 'locale', 'slug', 'updated_at', 'published_at'])
            : collect();

        $entries = [];
        if ($blogEnabled) {
            $entries[] = [
                'route' => 'blog.index',
                'parameters' => [],
                'localized_urls' => collect($supportedLocales)
                    ->mapWithKeys(fn (string $locale): array => [
                        $locale => route('blog.index', ['locale' => $locale]),
                    ])
                    ->all(),
                'lastmod' => optional($latestBlogPost?->updated_at ?? $latestBlogPost?->published_at ?? now())->toAtomString(),
            ];
        }

        foreach ($posts->groupBy('translation_group_uuid') as $groupedPosts) {
            $localizedUrls = $groupedPosts
                ->sortBy('locale')
                ->mapWithKeys(fn (BlogPost $post): array => [
                    (string) $post->locale => route('blog.show', ['locale' => $post->locale, 'slug' => $post->slug]),
                ])
                ->all();

            $lastModifiedAt = $groupedPosts
                ->map(fn (BlogPost $post) => $post->updated_at ?? $post->published_at)
                ->filter()
                ->sortDesc()
                ->first();

            $entries[] = [
                'localized_urls' => $localizedUrls,
                'lastmod' => optional($lastModifiedAt)->toAtomString(),
            ];
        }

        $cacheKey = 'seo:sitemap:blog:v1:'.sha1(json_encode([
            'blog_enabled' => $blogEnabled,
            'default' => $defaultLocale,
            'locales' => $supportedLocales,
            'latest_blog_updated_at' => optional($latestBlogPost?->updated_at)->toAtomString(),
            'latest_blog_published_at' => optional($latestBlogPost?->published_at)->toAtomString(),
            'posts' => $posts->map(fn (BlogPost $post): array => [
                'translation_group_uuid' => $post->translation_group_uuid,
                'locale' => $post->locale,
                'slug' => $post->slug,
                'updated_at' => optional($post->updated_at)->toAtomString(),
                'published_at' => optional($post->published_at)->toAtomString(),
            ])->all(),
        ]) ?: '');

        $payload = [
            'entries' => $entries,
            'supportedLocales' => $supportedLocales,
            'defaultLocale' => $defaultLocale,
        ];

        $xml = (! app()->environment('testing') && $cacheSeconds > 0)
            ? Cache::remember($cacheKey, $cacheSeconds, fn (): string => view('sitemap', $payload)->render())
            : view('sitemap', $payload)->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', $cacheHeader)
            ->header('X-Robots-Tag', 'noindex, follow');
    }
}
