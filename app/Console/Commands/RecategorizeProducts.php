<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\Discount;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\Store;
use App\Services\CategoryMappingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

// Re-files existing products that a pharmacy listed only under a mixed
// category ("Kosmetika ir higiena", Benu's seasonal promo shelf), after the
// scraper learned to send a real category for them. discounts:process never
// changes an existing product's category, so this is the step that fixes the
// products already in the DB (docs/evaistine.md, "Mixed categories").
//
// For each product the store offered under one of the --from strings, the
// new root category is, in order:
//   1. what the other pharmacies' offers of the same product say (majority),
//   2. the store's own latest category string for that offer,
//   3. GPT, by product name (CategoryMappingService, the categories:bulk-map
//      classifier).
// Only root categories are ever written (CLAUDE.md rule).
class RecategorizeProducts extends Command
{
    protected $signature = 'products:recategorize
                            {--store= : Store name, e.g. "Mano vaistinė"}
                            {--from=* : Mixed category strings the store used}
                            {--dry-run : Show the changes without saving}';

    protected $description = 'Re-file products a pharmacy listed only under a mixed category';

    /** @var array<string, int> */
    private array $rootsByName = [];

    /** @var array<string, array<string, int>> store id => store_category => root id */
    private array $mappers = [];

    public function handle(CategoryMappingService $mapping): int
    {
        $store = Store::where('name', $this->option('store'))->first();
        $from = array_values(array_filter((array) $this->option('from')));

        if (! $store || $from === []) {
            $this->error('Give --store and at least one --from category string.');

            return self::FAILURE;
        }

        $this->rootsByName = Category::whereNull('parent_id')->pluck('id', 'name')->all();
        $rootNames = array_flip($this->rootsByName);
        CategoryMapper::whereNotNull('category_id')->get()
            ->each(fn (CategoryMapper $m) => $this->mappers[$m->store][$m->store_category] = $m->category_id);

        $urls = DiscountTemp::where('store', $store->name)->whereIn('category', $from)->distinct()->pluck('product_url');
        $productIds = Discount::where('store_id', $store->id)->whereIn('product_url', $urls)->distinct()->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get(['id', 'name', 'category_id']);
        $this->info("{$products->count()} products from {$store->name} under: ".implode(', ', $from));

        $decided = [];
        $needGpt = collect();

        foreach ($products as $product) {
            $root = $this->fromOtherStores($product, $store, $from) ?? $this->fromStore($product, $store, $from);
            if ($root) {
                $decided[$product->id] = [$root, 'other pharmacies / store'];
            } else {
                $needGpt->push($product);
            }
        }

        foreach ($needGpt->chunk(20) as $chunk) {
            $result = $mapping->bulkMapProductsToCategories(
                $chunk->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
                $store->name
            ) ?? [];
            foreach ($result as $id => $categoryName) {
                if (isset($this->rootsByName[$categoryName])) {
                    $decided[$id] = [$this->rootsByName[$categoryName], 'GPT'];
                }
            }
        }

        $changes = $products->filter(fn (Product $p) => isset($decided[$p->id]) && $decided[$p->id][0] !== $p->category_id);
        $summary = $changes->groupBy(fn (Product $p) => ($rootNames[$p->category_id] ?? '-').' -> '.$rootNames[$decided[$p->id][0]])
            ->map->count()->sortDesc();

        $this->table(['Change', 'Products'], $summary->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line('Examples:');
        foreach ($changes->take(25) as $p) {
            $this->line("  #{$p->id} {$p->name}: ".($rootNames[$p->category_id] ?? '-')." -> {$rootNames[$decided[$p->id][0]]} ({$decided[$p->id][1]})");
        }
        $this->info("{$changes->count()} to change, ".($products->count() - count($decided)).' left undecided.');

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        foreach ($changes as $p) {
            Product::whereKey($p->id)->update(['category_id' => $decided[$p->id][0]]);
        }
        $this->info('Saved. Now run keywords:map-products, keywords:refresh-counts, deal-pool:refresh and cache:clear-discounts.');

        return self::SUCCESS;
    }

    // Majority root among the product's offers at other pharmacies, from each
    // offer's latest scraped category string.
    private function fromOtherStores(Product $product, Store $store, array $mixed): ?int
    {
        $votes = Discount::where('product_id', $product->id)
            ->where('store_id', '!=', $store->id)
            ->with('store:id,name')
            ->get(['store_id', 'product_url'])
            ->map(fn (Discount $d) => $this->resolve(
                $this->latestCategory($d->store?->name, $d->product_url),
                $d->store_id,
                $mixed
            ))
            ->filter();

        return $votes->isEmpty() ? null : $votes->countBy()->sortDesc()->keys()->first();
    }

    private function fromStore(Product $product, Store $store, array $mixed): ?int
    {
        return Discount::where('product_id', $product->id)->where('store_id', $store->id)->pluck('product_url')
            ->map(fn ($url) => $this->resolve($this->latestCategory($store->name, $url), $store->id, $mixed))
            ->filter()
            ->first();
    }

    private function latestCategory(?string $storeName, ?string $url): ?string
    {
        if (! $storeName || ! $url) {
            return null;
        }

        return DiscountTemp::where('store', $storeName)->where('product_url', $url)->latest('id')->value('category');
    }

    // A category string to a root id: our own root name (what bulk-map
    // writes), or an exact mapper of that store. Mixed strings don't count.
    private function resolve(?string $category, int $storeId, array $mixed): ?int
    {
        if (! $category || in_array($category, $mixed, true)) {
            return null;
        }

        return $this->rootsByName[$category] ?? $this->mappers[$storeId][$category] ?? null;
    }
}
