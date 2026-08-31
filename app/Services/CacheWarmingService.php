<?php

namespace App\Services;

use App\Http\Controllers\Api\ProductController;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Store;
use App\Support\CacheVersion;
use App\Support\CanonicalUrl;
use App\Support\PageHtmlCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

class CacheWarmingService
{
    protected $productController;

    protected ?Command $command = null;

    public function __construct(ProductController $productController)
    {
        $this->productController = $productController;
    }

    public function setCommand(?Command $command): void
    {
        $this->command = $command;
    }

    public function warmCriticalCaches()
    {
        $this->warmStoreCaches();
        $this->warmCategoryCaches();
        $this->warmStoreCategoryCaches();
        $this->warmBestByCategoryCaches();
        $this->warmPopularProductsCache();
        // Guest HTML before the (slower, bulk) all-discounts cache — real
        // visitors hitting the site mid-warm benefit from fast cached pages
        // as soon as this step finishes, rather than waiting for the whole
        // sequence. warmAllDiscountsCache() only backs getAllDiscounts()'s
        // own JSON cache, not page rendering, so nothing HTML-related
        // depends on it running first.
        $this->warmGuestHtmlCaches();
        $this->warmAllDiscountsCache();
    }

    public function warmBestByCategoryCaches()
    {
        $stores = Store::select('id', 'slug')->get();
        $this->section('best-by-category', $stores->count() + 1);

        $rootCacheKey = 'best_discounts_by_category_'.CacheVersion::suffix(['discounts']);
        $this->logWarm('best-by-category', '/akcijos', $rootCacheKey);
        $this->productController->getBestDiscountsByCategory();

        foreach ($stores as $store) {
            $cacheKey = "best_discounts_by_category_store_{$store->id}_".CacheVersion::suffix(['discounts']);
            $this->logWarm('best-by-category', "/akcijos/{$store->slug}", $cacheKey);
            $this->productController->getBestDiscountsByCategoryForStore($store->slug);
        }
    }

    public function warmAllDiscountsCache()
    {
        $cacheKey = $this->productController->resolveAllDiscountsCacheKey();
        $this->section('all discounts', 1);
        $this->logWarm('all', '/discount', $cacheKey);
        $this->productController->getAllDiscounts();
    }

    public function warmStoreCaches()
    {
        $stores = Store::select('slug')->get();
        $this->section('stores', $stores->count());

        foreach ($stores as $store) {
            $cacheKey = $this->productController->resolveDiscountsCacheKey($store->slug);
            $label = $this->productController->resolveDiscountsCacheLabel($store->slug);
            $this->logWarm('store', $label, $cacheKey);
            $this->productController->getDiscounts($store->slug);
        }
    }

    public function warmCategoryCaches()
    {
        $categories = Category::whereNull('parent_id')->select('slug')->get();
        $this->section('categories', $categories->count());

        foreach ($categories as $category) {
            $cacheKey = $this->productController->resolveDiscountsCacheKey($category->slug);
            $label = $this->productController->resolveDiscountsCacheLabel($category->slug);
            $this->logWarm('category', $label, $cacheKey);
            $this->productController->getDiscounts($category->slug);
        }
    }

    // $onlySlugs scopes the warm to a specific set of products (e.g. only
    // the ones a single store-processing batch actually touched) instead of
    // the whole catalog — product_with_similar's cache key isn't wired into
    // CacheVersion at all (see productWithSimilarCacheKey(), a flat 7-day
    // TTL), so without scoping, every batch re-warms every product site-wide
    // regardless of whether that batch changed it.
    public function warmPopularProductsCache(?array $onlySlugs = null)
    {
        $query = Product::withCount('discounts')->having('discounts_count', '>=', 1);

        if ($onlySlugs !== null) {
            $query->whereIn('slug', $onlySlugs);
        } else {
            $query->orderBy('discounts_count', 'desc');
        }

        $productsWithDiscounts = $query->get();

        $this->section('products with-similar', $productsWithDiscounts->count());

        $progressBar = $this->startProgressBar($productsWithDiscounts->count());

        foreach ($productsWithDiscounts as $product) {
            $cacheKey = ProductController::productWithSimilarCacheKey($product->slug);
            $label = "/product/{$product->slug}/with-similar";
            $this->logWarm('product', $label, $cacheKey);
            $this->productController->getProductWithSimilar($product->slug);

            $progressBar?->advance();
        }

        $this->finishProgressBar($progressBar);
    }

    public function getProductsWithDiscountsCount()
    {
        return Product::withCount('discounts')
            ->having('discounts_count', '>=', 1)
            ->count();
    }

    public function warmStoreCategoryCaches()
    {
        $pairs = $this->storeCategoryPairsQuery()->get();
        $this->section('store+category', $pairs->count());

        foreach ($pairs as $pair) {
            $cacheKey = $this->productController->resolveDiscountsCacheKey($pair->store_slug, $pair->category_slug);
            $label = $this->productController->resolveDiscountsCacheLabel($pair->store_slug, $pair->category_slug);
            $this->logWarm('store+category', $label, $cacheKey);
            $this->productController->getDiscounts($pair->store_slug, $pair->category_slug);
        }
    }

    public function getStoreCategoryPairsCount(): int
    {
        return $this->storeCategoryPairsQuery()->get()->count();
    }

    protected function storeCategoryPairsQuery()
    {
        return Discount::query()
            ->join('products', 'discounts.product_id', '=', 'products.id')
            ->join('stores', 'discounts.store_id', '=', 'stores.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('stores.slug as store_slug', 'categories.slug as category_slug')
            ->distinct();
    }

    public function warmGuestHtmlCaches(): void
    {
        // HTML warm renders Blade with the current machine's APP_URL — only
        // production may write page_html keys (shared Redis + local nuolaidos.wip
        // would otherwise poison @vite/Livewire/storage URLs for prod guests).
        if (! app()->environment('production')) {
            return;
        }

        $paths = collect(['/', '/akcijos']);

        foreach (Store::pluck('slug') as $slug) {
            $paths->push("/akcijos/{$slug}");
        }

        foreach (Category::whereNull('parent_id')->pluck('slug') as $slug) {
            $paths->push("/akcijos/{$slug}");
        }

        foreach ($this->storeCategoryPairsQuery()->get() as $pair) {
            $paths->push("/akcijos/{$pair->store_slug}/{$pair->category_slug}");
        }

        $paths = $paths->unique()->values();
        $this->section('guest page html', $paths->count());

        // Was config('app.url') (api.liachovskis.com, the pre-cutover
        // staging domain from deploy.sh's PROD_APP_URL default) — the real
        // HTTP request this makes carries that Host header, and Laravel's
        // Paginator resolves its own path/first_page_url/next_page_url
        // straight from the incoming request's URL (not through the URL
        // facade, so PageHtmlCache's forceRootUrl doesn't cover it) —
        // confirmed live: every paginated listing's pagination links baked
        // in api.liachovskis.com. Warming against the real canonical domain
        // fixes this at the source instead of patching Paginator's resolver.
        $origin = CanonicalUrl::origin();

        foreach ($paths as $path) {
            $cacheKey = PageHtmlCache::cacheKey($path);
            $this->logWarm('page-html', $path, $cacheKey);

            if (Cache::has($cacheKey)) {
                continue;
            }

            // A real loopback HTTP request, not app()->handle() in-process:
            // Livewire tracks whether it has injected its <script>/<style>
            // tags via container-scoped state that survives app()->handle()
            // sub-requests within the same long-running artisan process, so
            // only the FIRST path warmed here got real @livewireScripts —
            // every later cached page silently cached HTML with no Livewire
            // script tag at all (Alpine never boots, wire:click/x-data dead
            // in the browser). A separate HTTP round-trip is a separate
            // PHP-FPM worker, so that state can't leak between paths.
            //
            // One slow/timed-out page must not sink the rest of this loop —
            // an uncaught ConnectionException here used to kill the whole
            // warm run partway through, silently leaving every path AFTER
            // whichever one failed un-warmed (confirmed live: half the
            // page_html cache was hours older than the other half, all
            // from runs that died at the same page). Log and move on.
            try {
                Http::timeout(30)->get("{$origin}{$path}");
            } catch (\Throwable $e) {
                Log::warning("cache:warm page-html failed for {$path}: ".$e->getMessage());
            }
        }
    }

    public function warmFavoritesCache()
    {
        $categories = Category::whereNull('parent_id')->get();
        $this->section('favorites', 1 + $categories->count());

        $homeKey = $this->productController->resolveFavoriteHomeCacheKey();
        $this->logWarm('favorite-home', '/favorite/home', $homeKey);
        $this->productController->getFavoriteHome();

        foreach ($categories as $category) {
            $cacheKey = $this->productController->resolveFavoriteCategoryCacheKey($category->id);
            $label = "/favorite/category/{$category->id}";
            $this->logWarm('favorite-category', $label, $cacheKey);
            $this->productController->getFavoriteCategory($category->id);
        }
    }

    protected function startProgressBar(int $count): ?ProgressBar
    {
        if (! $this->command || $this->command->getOutput()->isVerbose()) {
            return null;
        }

        $bar = $this->command->getOutput()->createProgressBar($count);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%/%estimated:-6s%');
        $bar->start();

        return $bar;
    }

    protected function finishProgressBar(?ProgressBar $bar): void
    {
        if (! $bar) {
            return;
        }

        $bar->finish();
        $this->command->newLine();
    }

    protected function section(string $name, int $count): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->newLine();
        $this->command->info("→ {$name} ({$count})");
    }

    protected function logWarm(string $type, string $label, string $cacheKey): void
    {
        if (! $this->command || ! $this->command->getOutput()->isVerbose()) {
            return;
        }

        $status = Cache::has($cacheKey) ? 'HIT' : 'MISS';

        $this->command->line("  [{$type}] {$status} {$label} → {$cacheKey}");
    }
}
