<?php

namespace App\Http\Controllers\Content;

use App\Support\Localization\LocalizedRouteService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MarketingSitemapController
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
        $solutionSlugs = SolutionPageController::slugs();

        $staticLocalizedRoutes = array_values(array_filter(
            $this->localizedRouteService->sitemapStaticLocalizedRoutes(),
            static fn (string $routeName): bool => $routeName !== 'blog.index'
        ));

        $entries = [];
        $now = Carbon::now()->toAtomString();

        foreach ($staticLocalizedRoutes as $routeName) {
            $entries[] = [
                'route' => $routeName,
                'parameters' => [],
                'lastmod' => $now,
            ];
        }

        foreach ($solutionSlugs as $solutionSlug) {
            $entries[] = [
                'route' => 'solutions.show',
                'parameters' => ['slug' => $solutionSlug],
                'lastmod' => $now,
            ];
        }

        $cacheKey = 'seo:sitemap:marketing:v1:'.sha1(json_encode([
            'locales' => $supportedLocales,
            'default' => $defaultLocale,
            'routes' => $staticLocalizedRoutes,
            'solutions' => $solutionSlugs,
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
