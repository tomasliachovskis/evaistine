<?php

namespace App\Console\Commands;

use App\Models\KeywordPage;
use App\Models\KeywordPageProduct;
use App\Models\Product;
use App\Services\KeywordPageCategoryResolver;
use App\Support\CacheVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Rebuilds keyword_page_products — the precomputed product -> keyword page
// mapping product pages use for their "Susijusios akcijos" cross-links.
// Batch job, not a live per-request scan: for each published keyword page,
// finds candidate products via its category tree, then scores each by the
// same exclude_terms/search_terms/brands filters the page's own listing
// already uses (KeywordPageService::passesKeywordFilters), just applied
// product-first instead of discount-first.
class MapProductsToKeywordPages extends Command
{
    protected $signature = 'keywords:map-products {--page= : Only rebuild for this one keyword page slug}';

    protected $description = 'Rebuild the keyword_page_products mapping used for product-page keyword cross-links';

    public function __construct(private KeywordPageCategoryResolver $categoryResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = KeywordPage::query()->published();

        if ($slug = $this->option('page')) {
            $query->where('slug', $slug);
        }

        $pages = $query->get();
        $totalRows = 0;

        foreach ($pages as $page) {
            $rows = $this->mapPage($page);
            $totalRows += $rows;
            $this->info("{$page->slug}: {$rows} products mapped");
        }

        // Only prune orphaned pages (unpublished/deleted) when this ran for
        // every page — a single --page run shouldn't delete every other
        // page's rows.
        if ($this->option('page') === null) {
            KeywordPageProduct::whereNotIn('keyword_page_id', $pages->pluck('id'))->delete();
        }

        CacheVersion::bump('keywords');

        $this->info("Done. {$totalRows} total rows across {$pages->count()} pages.");

        return self::SUCCESS;
    }

    private function mapPage(KeywordPage $page): int
    {
        $categorySlugs = (array) ($page->category_slugs ?? []);
        $categoryIds = empty($categorySlugs) ? [] : $this->categoryResolver->resolvePrimaryCategoryTreeIds($categorySlugs);

        if (!empty($categorySlugs) && empty($categoryIds)) {
            // category_slugs set but didn't resolve to any real category —
            // no safe fallback, just no matches for this page.
            KeywordPageProduct::where('keyword_page_id', $page->id)->delete();

            return 0;
        }

        $searchTerms = collect((array) ($page->search_terms ?? []))
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter()
            ->values();
        $excludeTerms = collect((array) ($page->exclude_terms ?? []))
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter()
            ->values();
        $brands = collect((array) ($page->brands ?? []))
            ->map(fn ($b) => mb_strtolower(trim((string) $b)))
            ->filter()
            ->values();

        if ($searchTerms->isEmpty() && $brands->isEmpty()) {
            KeywordPageProduct::where('keyword_page_id', $page->id)->delete();

            return 0;
        }

        $productQuery = Product::query()->select(['id', 'name', 'brand']);
        if (!empty($categoryIds)) {
            $productQuery->whereIn('category_id', $categoryIds);
        }

        $rows = [];
        $now = now();

        $productQuery->chunkById(2000, function ($products) use (&$rows, $searchTerms, $excludeTerms, $brands, $page, $now) {
            foreach ($products as $product) {
                $nameLower = mb_strtolower($product->name);
                $brandLower = mb_strtolower(trim((string) $product->brand));

                $excluded = $excludeTerms->contains(
                    fn ($term) => $term !== '' && (mb_strpos($nameLower, $term) !== false || mb_strpos($brandLower, $term) !== false)
                );
                if ($excluded) {
                    continue;
                }

                $bestLength = 0;
                foreach ($searchTerms as $term) {
                    if (mb_strpos($nameLower, $term) !== false) {
                        $bestLength = max($bestLength, mb_strlen($term));
                    }
                }
                if ($brandLower !== '' && $brands->contains($brandLower)) {
                    $bestLength = max($bestLength, mb_strlen($brandLower));
                }

                if ($bestLength > 0) {
                    $rows[] = [
                        'keyword_page_id' => $page->id,
                        'product_id' => $product->id,
                        'score' => $bestLength,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        });

        DB::transaction(function () use ($page, $rows) {
            KeywordPageProduct::where('keyword_page_id', $page->id)->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                KeywordPageProduct::insert($chunk);
            }
        });

        return count($rows);
    }
}
