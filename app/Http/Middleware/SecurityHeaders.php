<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to all responses.
 *
 * These headers tell browsers how to handle your content securely:
 *
 * 1. Strict-Transport-Security (HSTS):
 *    Forces browsers to ONLY use HTTPS for your domain for 1 year.
 *    Prevents "SSL stripping" attacks where hackers downgrade to HTTP.
 *
 * 2. X-Content-Type-Options:
 *    Stops browsers from "guessing" file types.
 *    Prevents XSS attacks via malicious files pretending to be images.
 *
 * 3. X-Frame-Options:
 *    Blocks your site from being embedded in iframes on other sites.
 *    Prevents "clickjacking" where hackers overlay invisible frames.
 *
 * 4. Referrer-Policy:
 *    Controls what URL information is sent when users click links.
 *    Protects sensitive URLs/tokens from leaking to external sites.
 *
 * 5. Permissions-Policy:
 *    Restricts browser features like camera, microphone, geolocation.
 *    Reduces attack surface by disabling unused features.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers
 * @see https://securityheaders.com for testing your headers
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add HSTS in production with HTTPS
        // This tells browsers: "Always use HTTPS for my domain"
        // max-age=31536000 = 1 year
        // includeSubDomains = also applies to *.yourdomain.com
        if (app()->isProduction() && $request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Prevent browsers from MIME-sniffing (guessing) content types
        // Attack example: Hacker uploads malicious.js renamed as image.png
        // Without this header, browser might execute it as JavaScript
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Block this page from being loaded in iframes on other domains
        // Attack example: Evil site puts your login page in invisible iframe,
        // user thinks they're clicking their site but actually clicking yours
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Control what information is sent in the Referer header
        // "strict-origin-when-cross-origin" means:
        // - Same site: send full URL
        // - External site + HTTPS: send only domain (not full path)
        // - External site + HTTP: send nothing
        // Protects URLs like /reset-password?token=SECRET from leaking
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Disable browser features we don't need
        // Each feature disabled = less attack surface
        // Your SaaS kit likely doesn't need camera, microphone, or geolocation
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self)'
        );

        // Content Security Policy (CSP) - mitigates XSS and injection attacks
        // 'unsafe-inline' and 'unsafe-eval' needed for Livewire/Alpine.js
        // Billing provider domains (Paddle/Stripe) whitelisted for checkout
        // Customize based on your actual third-party integrations

        // In development, Vite runs on port 5173 (or 5174 if busy)
        // We need to allow multiple hosts because you might visit localhost:8000
        // but Vite might be serving from saas-kit.test or 127.0.0.1
        $viteDevServer = '';
        $viteWss = '';
        // Allow https: for form actions to prevent blocking seemingly valid secure submissions
        $formAction = "'self' https:";

        if (app()->isLocal()) {
            $hosts = array_unique([
                $request->getHost(),
                'localhost',
                '127.0.0.1',
                'saas-kit.test',
            ]);

            foreach ($hosts as $h) {
                if (str_contains($h, ':')) {
                    // IPv6 literals are rejected by CSP in browsers like Chrome.
                    continue;
                }

                // Allow http/https and ws/wss on both ports for maximum compatibility
                $viteDevServer .= " http://{$h}:5173 http://{$h}:5174 https://{$h}:5173 https://{$h}:5174";
                $viteWss .= " ws://{$h}:5173 ws://{$h}:5174 wss://{$h}:5173 wss://{$h}:5174";
            }

            // Allow posting to any local http/https origin while developing.
            $formAction = "'self' http: https:";
        }

        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' ".implode(' ', config('saas.security.csp_domains.script', [])).($viteDevServer ? " {$viteDevServer}" : ''),
            "script-src-elem 'self' 'unsafe-inline' ".implode(' ', config('saas.security.csp_domains.script', [])).($viteDevServer ? " {$viteDevServer}" : ''),
            "style-src 'self' 'unsafe-inline' ".implode(' ', config('saas.security.csp_domains.style', [])).($viteDevServer ? " {$viteDevServer}" : ''),
            "style-src-elem 'self' 'unsafe-inline' ".implode(' ', config('saas.security.csp_domains.style', [])).($viteDevServer ? " {$viteDevServer}" : ''),
            "font-src 'self' ".implode(' ', config('saas.security.csp_domains.font', [])),
            "img-src 'self' ".implode(' ', config('saas.security.csp_domains.img', [])),
            "connect-src 'self' ".implode(' ', config('saas.security.csp_domains.connect', [])).($viteDevServer ? " {$viteDevServer} {$viteWss}" : ''),
            "frame-src 'self' ".implode(' ', config('saas.security.csp_domains.frame', [])),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action {$formAction}",
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        return $response;
    }
}
