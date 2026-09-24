<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\Discount;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\Store;
use App\Support\EnergyDrinkCategory;
use Illuminate\Console\Command;

// Product::firstOrCreate() (see ProcessDiscounts::findOrCreateProduct) only
// ever sets category_id at creation time — it's never updated afterward,
// even when category_mappers is later corrected (a coarse mapper gets
// re-mapped, a previously-unmapped raw category string gets mapped, etc.).
// A product created before a mapping fix stays on the old, wrong
// category_id forever. Found via a real example this session: "Sviestas,
// 82 %" stuck at category_id=193 (Bakalėja) while every one of its raw
// scraped rows says "Pieno produktai ir kiaušiniai" — the same class of bug
// as the discounts:archive-expired / discount_temp.processed staleness
// already fixed elsewhere, just for products.category_id instead.
//
// This command re-resolves each product's category the exact same way
// ProcessDiscounts::resolveCategoryId() would right now (same pet-keyword
// override, same exact/2-part/1-part mapper fallback), using its most
// recent matching discount_temp row(s) by product_url, and corrects
// category_id where it disagrees.
class FixStaleProductCategories extends Command
{
    protected $signature = 'products:fix-stale-categories {--dry-run : Report what would change without writing}';

    protected $description = 'Re-resolve products.category_id from current category_mappers state and fix stale values';

    /** @var array<string, int> */
    private array $categoriesByName = [];

    /** @var array<int, list<CategoryMapper>> */
    private array $mappersByStoreId = [];

    public function handle(): int
    {
        $this->categoriesByName = Category::pluck('id', 'name')->all();

        $this->mappersByStoreId = [];
        foreach (CategoryMapper::orderBy('id')->get() as $mapper) {
            $this->mappersByStoreId[$mapper->store][] = $mapper;
        }

        $stores = Store::all()->keyBy('id');
        $dryRun = (bool) $this->option('dry-run');

        $checked = 0;
        $updated = 0;
        $samples = [];

        Product::whereHas('discounts')
            ->with('discounts')
            ->chunkById(500, function ($products) use ($stores, $dryRun, &$checked, &$updated, &$samples) {
                foreach ($products as $product) {
                    $resolved = $this->resolveProductCategory($product, $stores);
                    $checked++;

                    if ($resolved === null || $resolved === (int) $product->category_id) {
                        continue;
                    }

                    if (count($samples) < 30) {
                        $samples[] = "{$product->name} | {$product->category_id} -> {$resolved}";
                    }

                    if (!$dryRun) {
                        $product->update(['category_id' => $resolved]);
                    }

                    $updated++;
                }
            });

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Checked {$checked} products, ".($dryRun ? 'would update' : 'updated')." {$updated}.");

        foreach ($samples as $s) {
            $this->line("  {$s}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Store>  $stores
     */
    private function resolveProductCategory(Product $product, $stores): ?int
    {
        $votes = [];

        foreach ($product->discounts as $discount) {
            $store = $stores->get($discount->store_id);
            if (!$store || empty($discount->product_url)) {
                continue;
            }

            $temp = DiscountTemp::where('product_url', $discount->product_url)
                ->orderByDesc('created_at')
                ->first();

            if (!$temp || empty($temp->category)) {
                continue;
            }

            $resolved = EnergyDrinkCategory::apply($this->resolveCategoryId($temp->name, $temp->category, $store), $temp->name);
            if ($resolved) {
                $votes[$resolved] = ($votes[$resolved] ?? 0) + 1;
            }
        }

        if (empty($votes)) {
            return null;
        }

        arsort($votes);

        return (int) array_key_first($votes);
    }

    // Mirrors ProcessDiscounts::resolveCategoryId() exactly (pet-keyword
    // override, categoriesByName direct hit, then exact/2-part/1-part
    // mapper fallback) — deliberately not shared code, this command is a
    // one-time-use maintenance tool, not part of the regular pipeline.
    private function resolveCategoryId(string $productName, string $categoryName, Store $store): ?int
    {
        $productNameLower = strtolower($productName);
        $petKeywords = ['šunų', 'ėdalas', 'kačių', 'gyvūnų'];

        foreach ($petKeywords as $keyword) {
            if (mb_strpos($productNameLower, $keyword) !== false) {
                return 619;
            }
        }

        $storeCategory = str_replace(['https://iki.lt/'], '', $categoryName);

        if (isset($this->categoriesByName[$categoryName])) {
            return $this->categoriesByName[$categoryName];
        }

        $mappers = $this->mappersByStoreId[$store->id] ?? [];

        $mapper = $this->findMapper($mappers, $categoryName, exact: true);
        if ($mapper && $mapper->category_id) {
            return $mapper->category_id;
        }

        $mapper = $this->findMapper($mappers, $categoryName, exact: false);
        if ($mapper && $mapper->category_id) {
            return $mapper->category_id;
        }

        if (str_contains($storeCategory, '/')) {
            $parts = explode('/', $storeCategory);

            if (count($parts) >= 2) {
                $twoParts = $parts[0].'/'.$parts[1];

                $mapper = $this->findMapper($mappers, $twoParts, exact: true, allowTrailingSlash: true);
                if ($mapper && $mapper->category_id) {
                    return $mapper->category_id;
                }

                $mapper = $this->findMapper($mappers, $twoParts, exact: false);
                if ($mapper && $mapper->category_id) {
                    return $mapper->category_id;
                }
            }

            $firstPart = $parts[0];

            $mapper = $this->findMapper($mappers, $firstPart, exact: true);
            if ($mapper && $mapper->category_id) {
                return $mapper->category_id;
            }

            $mapper = $this->findMapper($mappers, $firstPart, exact: false);
            if ($mapper && $mapper->category_id) {
                return $mapper->category_id;
            }
        }

        return null;
    }

    /**
     * @param  list<CategoryMapper>  $mappers
     */
    private function findMapper(array $mappers, string $needle, bool $exact, bool $allowTrailingSlash = false): ?CategoryMapper
    {
        foreach ($mappers as $mapper) {
            if ($exact) {
                if ($mapper->store_category === $needle) {
                    return $mapper;
                }
                if ($allowTrailingSlash && $mapper->store_category === $needle.'/') {
                    return $mapper;
                }

                continue;
            }

            if (str_starts_with($mapper->store_category, $needle)) {
                return $mapper;
            }
        }

        return null;
    }
}
