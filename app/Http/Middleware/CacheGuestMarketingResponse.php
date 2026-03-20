<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheGuestMarketingResponse
{
    private const CSRF_PLACEHOLDER = '__CSRF_TOKEN_PLACEHOLDER__';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldUseCache($request)) {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $this->hydrateCachedResponse($cached);
        }

        $response = $next($request);

        if (! $this->isCacheableResponse($response)) {
            return $response;
        }

        Cache::put($cacheKey, [
            'content' => str_replace(csrf_token(), self::CSRF_PLACEHOLDER, $response->getContent()),
            'status' => $response->getStatusCode(),
            'content_type' => (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
        ], now()->addSeconds((int) config('saas.performance.marketing_page_cache_seconds', 300)));

        $response->headers->set('X-Response-Cache', 'miss');

        return $response;
    }

    private function shouldUseCache(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->user() !== null) {
            return false;
        }

        if (! $request->routeIs([
            'home',
            'features',
            'pricing',
            'solutions.index',
            'solutions.show',
            'blog.index',
            'blog.show',
            'docs.index',
            'docs.show',
            'roadmap',
        ])) {
            return false;
        }

        if (! $request->hasSession()) {
            return true;
        }

        $session = $request->session();

        return ! $session->hasOldInput()
            && ! $session->has('errors')
            && empty($session->get('dismissed_announcements', []));
    }

    private function isCacheableResponse(Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type', ''), 'text/html');
    }

    /**
     * @param  array{content: string, status: int, content_type: string}  $cached
     */
    private function hydrateCachedResponse(array $cached): Response
    {
        $response = response(
            str_replace(self::CSRF_PLACEHOLDER, csrf_token(), $cached['content']),
            $cached['status']
        );

        $response->headers->set('Content-Type', $cached['content_type']);
        $response->headers->set('X-Response-Cache', 'hit');

        return $response;
    }

    private function cacheKey(Request $request): string
    {
        return 'marketing_response:'.sha1($request->fullUrl().'|'.app()->getLocale());
    }
}
