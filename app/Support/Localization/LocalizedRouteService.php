<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalizedRouteService
{
    /**
     * @return array<int, string>
     */
    public function supportedLocales(): array
    {
        $locales = array_keys(config('saas.locales.supported', ['en' => 'English']));
        $locales = array_values(array_filter($locales, static fn (mixed $locale): bool => is_string($locale) && $locale !== ''));

        return $locales === [] ? ['en'] : $locales;
    }

    public function defaultLocale(): string
    {
        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));

        return $this->isSupportedLocale($defaultLocale)
            ? $defaultLocale
            : ($this->supportedLocales()[0] ?? 'en');
    }

    public function localePattern(): string
    {
        return implode('|', $this->supportedLocales());
    }

    public function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales(), true);
    }

    public function localizedRouteName(?string $routeName): ?string
    {
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        $candidate = str_starts_with($routeName, 'legacy.')
            ? substr($routeName, strlen('legacy.'))
            : $routeName;

        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return $this->routeAcceptsLocale($candidate) ? $candidate : null;
    }

    /**
     * @return array<int, string>
     */
    public function sitemapStaticLocalizedRoutes(): array
    {
        $routes = [
            'home',
            'features',
            'pricing',
            'solutions.index',
            'docs.index',
        ];

        if ((bool) config('saas.features.blog', true)) {
            $routes[] = 'blog.index';
        }

        if ((bool) config('saas.features.roadmap', true)) {
            $routes[] = 'roadmap';
        }

        return $routes;
    }

    public function localizedUrl(string $routeName, string $locale, array $parameters = [], bool $absolute = true): string
    {
        $targetLocale = $this->isSupportedLocale($locale) ? $locale : $this->defaultLocale();

        return route($routeName, array_merge($parameters, ['locale' => $targetLocale]), $absolute);
    }

    public function resolveLocaleSwitchRedirect(Request $request, string $locale, string $redirect): string
    {
        $targetLocale = $this->isSupportedLocale($locale) ? $locale : $this->defaultLocale();
        $fallback = $this->localizedUrl('home', $targetLocale);

        if ($redirect === '') {
            return $fallback;
        }

        $parts = parse_url($redirect);
        if ($parts === false) {
            return $fallback;
        }

        $host = $parts['host'] ?? null;
        if (is_string($host) && $host !== '' && ! hash_equals(strtolower($request->getHost()), strtolower($host))) {
            return $fallback;
        }

        $path = $this->normalizePath((string) ($parts['path'] ?? '/'));
        $queryParameters = $this->parseQueryParameters((string) ($parts['query'] ?? ''));
        unset($queryParameters['lang']);

        $matchedRoute = $this->matchRoute($request, $path, $queryParameters);
        $localizedRouteName = $this->localizedRouteName($matchedRoute?->getName());

        if (! $localizedRouteName) {
            return $this->buildInternalTarget($path, $queryParameters, (string) ($parts['fragment'] ?? ''));
        }

        $routeParameters = $matchedRoute?->parametersWithoutNulls() ?? [];
        unset($routeParameters['locale']);

        $localizedPath = $this->localizedUrl($localizedRouteName, $targetLocale, $routeParameters, false);
        $query = http_build_query($queryParameters);
        $fragment = (string) ($parts['fragment'] ?? '');

        return $localizedPath
            .($query !== '' ? '?'.$query : '')
            .($fragment !== '' ? '#'.$fragment : '');
    }

    private function routeAcceptsLocale(string $routeName): bool
    {
        $route = RouteFacade::getRoutes()->getByName($routeName);

        if (! $route instanceof Route) {
            return false;
        }

        return in_array('locale', $route->parameterNames(), true);
    }

    /**
     * @param  array<string, mixed>  $queryParameters
     */
    private function matchRoute(Request $request, string $path, array $queryParameters): ?Route
    {
        try {
            $probeRequest = Request::create($path, 'GET', $queryParameters);
            $probeRequest->headers->set('HOST', $request->getHost());
            $probeRequest->server->set('HTTP_HOST', $request->getHost());

            return RouteFacade::getRoutes()->match($probeRequest);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseQueryParameters(string $queryString): array
    {
        if ($queryString === '') {
            return [];
        }

        parse_str($queryString, $queryParameters);

        return is_array($queryParameters) ? $queryParameters : [];
    }

    /**
     * Ensure redirect paths are always internal absolute paths.
     */
    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = '/'.ltrim($normalized, '/');

        return preg_replace('#/+#', '/', $normalized) ?? '/';
    }

    /**
     * @param  array<string, mixed>  $queryParameters
     */
    private function buildInternalTarget(string $path, array $queryParameters, string $fragment): string
    {
        $query = http_build_query($queryParameters);

        return $path
            .($query !== '' ? '?'.$query : '')
            .($fragment !== '' ? '#'.$fragment : '');
    }
}
