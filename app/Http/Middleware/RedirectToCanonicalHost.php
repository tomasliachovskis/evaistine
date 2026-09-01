<?php

namespace App\Http\Middleware;

use App\Support\CanonicalUrl;
use Closure;
use Illuminate\Http\Request;

// This app is also reachable at a staging host (api.superakcijos.lt) ahead
// of / alongside the public-domain cutover — SitemapController::robots()
// already blocks crawling there, but robots.txt doesn't stop an already-
// linked URL from being indexed, and PageHtmlCache stores canonical/og:url
// as root-relative (by design, so cached HTML resolves correctly regardless
// of which host serves it) — which means a page served from the staging
// host self-referenced instead of pointing at the real domain. A 301 to the
// canonical host closes that gap outright: nothing on the staging host is
// ever visible to a visitor or crawler to begin with.
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->environment('production')) {
            return $next($request);
        }

        // Internal-only paths never carry the real public Host header
        // (scrapers POST to 127.0.0.1, the LB/uptime monitor hits _health)
        // and must never be redirected.
        if ($request->is('api/*') || $request->is('_health')) {
            return $next($request);
        }

        $canonicalHost = parse_url(CanonicalUrl::build('/'), PHP_URL_HOST);

        if (in_array($request->getHost(), [$canonicalHost, "www.{$canonicalHost}"], true)) {
            return $next($request);
        }

        return redirect()->to(CanonicalUrl::origin().$request->getRequestUri(), 301);
    }
}
