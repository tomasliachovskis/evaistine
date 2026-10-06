<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\CouponWebsite;
use App\Support\CanonicalUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

// Ported from discount's robots.ts / sitemap.ts / product-sitemap/[page]/route.ts.
// Reuses ProductController::getSitemap()/getSitemapProducts() in-process (same
// CacheVersion-backed cache those already have) instead of re-deriving the
// underlying queries here.
class SitemapController extends Controller
{
    private const PRODUCTS_PER_PAGE = 20000;

    private const BLOCKED_BOTS = [
        'Exabot', 'Jetbot', 'AskJeeves', 'Copernic', 'CherryPicker',
        'EmailCollector', 'EmailSiphon', 'WebBandit', 'WebCopier', 'ia_archiver',
    ];

    public function robots(Request $request)
    {
        // This app is currently also reachable at its staging host
        // (api.evaistine.lt) ahead of the public-domain nginx cutover —
        // block crawling there entirely rather than risk it getting indexed
        // as duplicate content. CanonicalUrl::BASE_URL already hardcodes the
        // real public domain, so anything else request()->getHost() returns
        // is by definition not-yet-cut-over staging.
        $canonicalHost = parse_url(CanonicalUrl::build('/'), PHP_URL_HOST);
        if (!in_array($request->getHost(), [$canonicalHost, "www.{$canonicalHost}"], true)) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
        }

        $productSitemapCount = $this->productSitemapPageCount();

        $lines = [
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /private/',
            'Disallow: /auth/',
            // Previously allowed + noindex-meta'd instead of blocked here, so
            // Googlebot would actually crawl the page and see the noindex tag
            // rather than showing "Indexed, though blocked by robots.txt".
            // That still let bots crawl and index /paieska/* pages in
            // practice, so block crawling outright instead.
            'Disallow: /paieska',
            'Disallow: /*?store=*',
            'Disallow: /*?category=*',
            'Disallow: /*?card=*',
            'Disallow: /*?plus=*',
            'Disallow: /*?order=*',
            'Disallow: /*&*',
            '',
        ];

        foreach (self::BLOCKED_BOTS as $bot) {
            $lines[] = "User-agent: {$bot}";
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }

        $lines[] = 'Sitemap: ' . CanonicalUrl::build('/sitemap.xml');
        for ($page = 1; $page <= $productSitemapCount; $page++) {
            $lines[] = 'Sitemap: ' . CanonicalUrl::build("/product-sitemap/{$page}");
        }

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap()
    {
        $data = $this->sitemapData();
        $defaultLastmod = $data['lastmod'] ?? now()->format('Y-m-d');

        $urls = [
            ['loc' => CanonicalUrl::build('/'), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => CanonicalUrl::build('/akcijos'), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => CanonicalUrl::build('/pigiausios-prekes'), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.85'],
            ['loc' => CanonicalUrl::build('/leidiniai'), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.85'],
            ['loc' => CanonicalUrl::build('/vaistines'), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => CanonicalUrl::build('/naujienos'), 'lastmod' => $defaultLastmod, 'changefreq' => 'weekly', 'priority' => '0.6'],
            ['loc' => CanonicalUrl::build('/apie'), 'lastmod' => $defaultLastmod, 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['loc' => CanonicalUrl::build('/privatumo-politika'), 'lastmod' => $defaultLastmod, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $leafletStoreSlugs = $data['leaflet_stores'] ?? $data['stores'] ?? [];
        foreach ($leafletStoreSlugs as $slug) {
            $urls[] = ['loc' => CanonicalUrl::build("/leidinys/{$slug}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.85'];
        }

        foreach ($data['leaflets'] ?? [] as $leaflet) {
            $urls[] = ['loc' => CanonicalUrl::build("/{$leaflet['path']}"), 'lastmod' => $leaflet['lastmod'] ?? $defaultLastmod, 'changefreq' => 'weekly', 'priority' => '0.8'];
        }

        foreach ($data['stores'] ?? [] as $slug) {
            $urls[] = ['loc' => CanonicalUrl::build("/{$slug}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.8'];
        }

        foreach ($data['store_category_pages'] ?? [] as $path) {
            $urls[] = ['loc' => CanonicalUrl::build("/{$path}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.6'];
        }

        foreach ($data['categories'] ?? [] as $slug) {
            $urls[] = ['loc' => CanonicalUrl::build("/{$slug}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.7'];
        }

        foreach ($data['keywords'] ?? [] as $slug) {
            $urls[] = ['loc' => CanonicalUrl::build("/{$slug}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.85'];
        }

        foreach ($data['blog_posts'] ?? [] as $post) {
            $urls[] = ['loc' => CanonicalUrl::build("/naujienos/{$post['slug']}"), 'lastmod' => $post['lastmod'] ?? $defaultLastmod, 'changefreq' => 'weekly', 'priority' => '0.5'];
        }

        foreach ($data['store_location_slugs'] ?? [] as $slug) {
            $urls[] = ['loc' => CanonicalUrl::build("/vaistines/{$slug}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'weekly', 'priority' => '0.6'];
        }

        foreach ($data['store_city_pages'] ?? [] as $path) {
            $urls[] = ['loc' => CanonicalUrl::build("/vaistines/{$path}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'weekly', 'priority' => '0.5'];
        }

        $urls[] = ['loc' => CanonicalUrl::build('/kuponai'), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.8'];

        $couponWebsiteSlugs = CouponWebsite::whereHas('coupons', fn ($q) => $q->active()->currentlyValid())
            ->pluck('slug');

        foreach ($couponWebsiteSlugs as $slug) {
            $urls[] = ['loc' => CanonicalUrl::build("/kuponai/{$slug}"), 'lastmod' => $defaultLastmod, 'changefreq' => 'daily', 'priority' => '0.6'];
        }

        return response()
            ->view('sitemap.urlset', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function productSitemap(Request $request, int $page)
    {
        $products = $this->productSitemapProducts($page);

        if (count($products) === 0) {
            // Same reasoning as the Next.js route this replaces: an in-range
            // page with zero products, or a fetch failure, is a signal
            // something's wrong upstream -- not a legitimately empty sitemap.
            // Serve a real error so crawlers back off and retry instead of
            // Search Console recording a false "0 pages discovered".
            return response('Service temporarily unavailable', 503);
        }

        $urls = array_map(fn ($p) => [
            'loc' => CanonicalUrl::build('/' . $p['path']),
            'lastmod' => $p['lastmod'],
            'changefreq' => 'weekly',
            'priority' => '0.6',
        ], $products);

        return response()
            ->view('sitemap.urlset', ['urls' => $urls])
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=3600');
    }

    private function sitemapData(): array
    {
        return App::make(ProductController::class)->getSitemap()->getData(true);
    }

    private function productSitemapProducts(int $page): array
    {
        $request = Request::create('/', 'GET', ['page' => $page]);
        $data = App::make(ProductController::class)->getSitemapProducts($request)->getData(true);

        return $data['products'] ?? [];
    }

    private function productSitemapPageCount(): int
    {
        $total = $this->sitemapData()['products_total'] ?? 0;

        return max(1, (int) ceil($total / self::PRODUCTS_PER_PAGE));
    }
}
