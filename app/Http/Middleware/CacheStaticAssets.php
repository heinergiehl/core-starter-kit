<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds Cache-Control headers for static assets served through Laravel.
 *
 * Vite-hashed assets (in /build/) are immutable and cached for 1 year.
 * Marketing images, branding, and fonts are cached for 7 days.
 */
class CacheStaticAssets
{
    /** @var array<string, string> Path prefix → Cache-Control header */
    private const CACHE_RULES = [
        '/build/' => 'public, max-age=31536000, immutable',  // Vite-hashed: 1 year
        '/marketing/' => 'public, max-age=604800',            // Marketing images: 7 days
        '/branding/' => 'public, max-age=604800',             // Branding assets: 7 days
        '/fonts/' => 'public, max-age=31536000, immutable',   // Fonts: 1 year
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = $request->getPathInfo();

        foreach (self::CACHE_RULES as $prefix => $cacheControl) {
            if (str_starts_with($path, $prefix)) {
                $response->headers->set('Cache-Control', $cacheControl);
                break;
            }
        }

        return $response;
    }
}
