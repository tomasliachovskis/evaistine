<?php

namespace App\Services;

use App\Models\KeywordPage;
use App\Models\KeywordPageProduct;
use App\Models\Product;
use App\Support\CacheVersion;
use Illuminate\Support\Facades\DB;

// Fills keyword_page_products, the one source of a keyword page's products:
// its grid, offer counts, home teasers and the product page's keyword links
// all read this table. Runs after every scrape batch (FinalizeScrapedStoresJob)
// and nightly, not per request.
//
// Each search term is searched on its own in the Meilisearch "products"
// index (all words must match, inflections via typo tolerance), brands match
// the product's brand exactly, then exclude_terms drop what doesn't belong.
class KeywordPageProductMapper
{
    // Short words ("d", "c", "b12") are prefix-matched by Meilisearch, so
    // "vitaminas d" would also hit "dengtos" or "daily". Such words must
    // stand alone in the name (or before a digit/hyphen: "D3", "D-MAX").
    private const SHORT_WORD = 3;

    public function __construct(
        private ProductSearchIndex $index,
        private KeywordPageCategoryResolver $categoryResolver,
    ) {
    }

    // Returns the number of products mapped to the page.
    public function mapPage(KeywordPage $page): int
    {
        $productIds = $this->matchProducts($page);
        $now = now();

        DB::transaction(function () use ($page, $productIds, $now) {
            KeywordPageProduct::where('keyword_page_id', $page->id)->delete();
            foreach (array_chunk($productIds, 1000, true) as $chunk) {
                $rows = [];
                foreach ($chunk as $productId => $score) {
                    $rows[] = [
                        'keyword_page_id' => $page->id,
                        'product_id' => $productId,
                        'score' => $score,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                KeywordPageProduct::insert($rows);
            }
        });

        CacheVersion::bump('keywords');

        return count($productIds);
    }

    /**
     * Product id => score (length of the longest term that matched it).
     *
     * @return array<int, int>
     */
    public function matchProducts(KeywordPage $page): array
    {
        $categorySlugs = (array) ($page->category_slugs ?? []);
        $categoryIds = $categorySlugs === [] ? [] : $this->categoryResolver->resolvePrimaryCategoryTreeIds($categorySlugs);
        if ($categorySlugs !== [] && $categoryIds === []) {
            return [];
        }

        $excludeTerms = $this->normalizedList($page->exclude_terms);
        $matches = [];

        foreach ($this->normalizedList($page->search_terms) as $term) {
            $shortWords = array_filter(preg_split('/\s+/u', $term), fn ($word) => mb_strlen($word) <= self::SHORT_WORD);

            foreach ($this->index->search($term, $categoryIds) as $hit) {
                $name = mb_strtolower($hit['name']);
                if (! $this->containsShortWords($name, $shortWords)) {
                    continue;
                }
                if ($this->isExcluded($name, mb_strtolower($hit['brand']), $excludeTerms)) {
                    continue;
                }
                $matches[$hit['id']] = max($matches[$hit['id']] ?? 0, mb_strlen($term));
            }
        }

        $brands = $this->normalizedList($page->brands);
        if ($brands !== []) {
            Product::query()
                ->select(['id', 'name', 'brand'])
                ->whereIn(DB::raw('LOWER(brand)'), $brands)
                ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
                ->each(function (Product $product) use (&$matches, $excludeTerms) {
                    $brand = mb_strtolower(trim((string) $product->brand));
                    if (! $this->isExcluded(mb_strtolower($product->name), $brand, $excludeTerms)) {
                        $matches[$product->id] = max($matches[$product->id] ?? 0, mb_strlen($brand));
                    }
                });
        }

        return $matches;
    }

    /** @param list<string> $shortWords */
    private function containsShortWords(string $name, array $shortWords): bool
    {
        foreach ($shortWords as $word) {
            if (! preg_match('/(?<![\pL\pN])' . preg_quote($word, '/') . '(?![\pL])/u', $name)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $excludeTerms */
    private function isExcluded(string $name, string $brand, array $excludeTerms): bool
    {
        foreach ($excludeTerms as $term) {
            if (mb_strpos($name, $term) !== false || ($brand !== '' && mb_strpos($brand, $term) !== false)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function normalizedList(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($value) => mb_strtolower(trim((string) $value)),
            (array) ($values ?? []),
        ))));
    }
}
