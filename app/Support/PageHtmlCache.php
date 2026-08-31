<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Full-page HTML cache for guest requests on "clean" URLs (no query string).
 * Invalidates automatically when CacheVersion::bump('discounts') runs — the
 * version suffix is part of every cache key.
 *
 * Same-site asset URLs are stored as root-relative paths (/build, /livewire,
 * /storage) so the browser always resolves them against whatever host the
 * visitor used. CSRF tokens are still neutralized/re-hydrated per request.
 * Livewire pages are safe to cache — UI loading states must use CSS :disabled
 * (e.g. group-disabled:) instead of wire:loading visibility toggles.
 */
class PageHtmlCache
{
    private const CSRF_PLACEHOLDER = '__PAGE_HTML_CSRF__';

    /** @deprecated Legacy cache entries only — new writes use relative paths. */
    private const APP_URL_PLACEHOLDER = '__PAGE_HTML_APP_URL__';

    private const TTL_SECONDS = 86400; // 24h — page caches are warmed on deploy via `cache:warm --type=page-html`

    public static function isEligible(Request $request): bool
    {
        if (auth()->check()) {
            return false;
        }

        return $request->query() === [];
    }

    public static function cacheKey(string $path): string
    {
        return 'page_html:'.sha1($path).'_'.CacheVersion::suffix(['discounts']);
    }

    public static function remember(Request $request, string $path, Closure $render): View|Response
    {
        if (! self::isEligible($request)) {
            return $render();
        }

        $key = self::cacheKey($path);
        $hit = Cache::has($key);

        $html = Cache::remember($key, self::TTL_SECONDS, function () use ($render) {
            return self::renderForCache($render);
        });

        return response(self::hydrateForResponse($html))
            ->header('X-Page-Cache', $hit ? 'HIT' : 'MISS');
    }

    public static function renderForCache(Closure $render): string
    {
        $canonical = self::canonicalOrigin();
        URL::forceRootUrl($canonical);

        try {
            $view = $render();

            if (! $view instanceof View) {
                return self::neutralizeForStorage((string) $view);
            }

            return self::neutralizeForStorage($view->render());
        } finally {
            URL::forceRootUrl($canonical);
        }
    }

    public static function neutralizeForStorage(string $html): string
    {
        $html = self::neutralizeCsrf($html);
        $html = self::toRelativeSameSiteUrls($html);
        $html = self::toRelativeMetaUrls($html);

        return $html;
    }

    public static function hydrateForResponse(string $html): string
    {
        return self::hydrateCsrf(self::hydrateAppUrl($html));
    }

    public static function neutralizeCsrf(string $html): string
    {
        if (! preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches)) {
            return $html;
        }

        return str_replace($matches[1], self::CSRF_PLACEHOLDER, $html);
    }

    public static function hydrateCsrf(string $html): string
    {
        if (! str_contains($html, self::CSRF_PLACEHOLDER)) {
            return $html;
        }

        return str_replace(self::CSRF_PLACEHOLDER, (string) csrf_token(), $html);
    }

    public static function hydrateAppUrl(string $html): string
    {
        $canonical = self::canonicalOrigin();

        // Legacy cache entries stored placeholder-prefixed asset URLs.
        $html = str_replace(self::APP_URL_PLACEHOLDER.'/', '/', $html);
        $html = str_replace(self::APP_URL_PLACEHOLDER, $canonical, $html);

        // Legacy cache entries with a raw IP/localhost/wrong host baked in.
        return self::rewriteLegacyAbsoluteSameSiteUrls($html);
    }

    private static function toRelativeSameSiteUrls(string $html): string
    {
        $paths = self::sameSitePathPattern();

        return preg_replace(
            '#https?://[^/"\'\s]+(?=/(?:'.$paths.'))#',
            '',
            $html
        ) ?? $html;
    }

    private static function toRelativeMetaUrls(string $html): string
    {
        $html = preg_replace_callback(
            '#(<link rel="canonical" href=")https?://[^/"]+([^"]*)#',
            fn (array $matches) => $matches[1].($matches[2] !== '' ? $matches[2] : '/'),
            $html
        ) ?? $html;

        return preg_replace_callback(
            '#(<meta property="og:url" content=")https?://[^/"]+([^"]*)#',
            fn (array $matches) => $matches[1].($matches[2] !== '' ? $matches[2] : '/'),
            $html
        ) ?? $html;
    }

    private static function rewriteLegacyAbsoluteSameSiteUrls(string $html): string
    {
        $paths = self::sameSitePathPattern();

        return preg_replace(
            '#https?://[^/"\'\s]+(?=/(?:'.$paths.'))#',
            '',
            $html
        ) ?? $html;
    }

    private static function sameSitePathPattern(): string
    {
        return 'build|livewire(?:-[a-f0-9]+)?|storage';
    }

    private static function canonicalOrigin(): string
    {
        // Was config('app.url') — that's whatever deploy.sh's PROD_APP_URL
        // set .env's APP_URL to (api.liachovskis.com, the pre-cutover
        // staging domain), not the real public domain. Every route()/url()
        // call rendered into cached HTML was forced onto that wrong host —
        // confirmed live: every link on every cached page pointed at
        // liachovskis.com instead of superakcijos.lt. CanonicalUrl::origin()
        // is the actual single source of truth for the public domain.
        return CanonicalUrl::origin();
    }
}
