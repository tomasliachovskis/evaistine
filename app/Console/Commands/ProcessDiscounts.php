<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\DiscountHistory;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\Store;
use App\Rules\StoreRules\IkiRules;
use App\Rules\StoreRules\LidlRules;
use App\Rules\StoreRules\MaximaRules;
use App\Rules\StoreRules\NorfaRules;
use App\Rules\StoreRules\RimiRules;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\CategoryMapper;
use App\Services\ProductNameNormalizer;

class ProcessDiscounts extends Command
{
    protected $signature = 'discounts:process';
    protected $description = 'Process new discounts from discount_temp table';

    private ProductNameNormalizer $normalizer;

    public function __construct(ProductNameNormalizer $normalizer)
    {
        parent::__construct();
        $this->normalizer = $normalizer;
    }

    public function handle()
    {
        $duplicates = DiscountTemp::whereNotNull('product_url')
            ->select('product_url')
            ->groupBy('product_url')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_url');

        foreach ($duplicates as $url) {
            dump($url);
            $records = DiscountTemp::where('product_url', $url)
                ->orderBy('id', 'desc')
                ->get();

            $firstRecord = $records->shift();
            foreach ($records as $record) {
                $record->delete();
            }
        }

        $tempDiscounts = DiscountTemp::all();

        foreach ($tempDiscounts as $tempDiscount) {
            $store = Store::where('name', $tempDiscount->store)->first();

            if (!$store) {
                $this->error("Store not found: {$tempDiscount->store}");
                continue;
            }

            $rules = $this->getStoreRules($store->name, $tempDiscount);

            if (!$rules->validate()) {
                $this->error("Invalid discount data for product: {$tempDiscount->id}");
                continue;
            }

            $normalizedOriginalPrice = $rules->normalizePrice($tempDiscount->original_price);
            $normalizedDiscountedPrice = $rules->normalizePrice($tempDiscount->discounted_price);

            $discountPercent = $tempDiscount->discount_percent;
            if (!empty($discountPercent)) {
                $discountPercent = preg_replace('/[^0-9]/', '', $discountPercent);
            }
            if (empty($discountPercent) && $normalizedOriginalPrice > 0 && $normalizedDiscountedPrice > 0) {
                $discountPercent = round((($normalizedOriginalPrice - $normalizedDiscountedPrice) / $normalizedOriginalPrice) * 100);
            } else if (empty($discountPercent)) {
                $discountPercent = null;
            }

            $startAt = $tempDiscount->start_at && strtotime($tempDiscount->start_at) ? $tempDiscount->start_at : null;
            $endAt = $tempDiscount->end_at && strtotime($tempDiscount->end_at) ? $tempDiscount->end_at : null;

            if ($startAt === null && $endAt === null) {
                $this->error("Invalid discount data for product: {$tempDiscount->id}");
                continue;
            }

            $normalizedProductName = $this->normalizer->normalize($tempDiscount->name);
            $productSlug = $this->normalizer->generateSlug($normalizedProductName);

            $product = Product::firstOrCreate(
                ['slug' => $productSlug],
                [
                    'name' => $normalizedProductName,
                    'slug' => $productSlug,
                    'description' => '',
                    'category_id' => $this->assignCategory($tempDiscount, $store),
                    'image_url' => $tempDiscount->image_url,
                ]
            );

            DiscountHistory::create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'product_url' => $tempDiscount->product_url,
                'original_price' => $normalizedOriginalPrice,
                'discounted_price' => $normalizedDiscountedPrice,
                'discount_percent' => $discountPercent,
                'condition' => $tempDiscount->condition,
                'card' => $tempDiscount->card,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);

            // $tempDiscount->delete();
        }

        $this->info('Discounts processed successfully');
    }

    private function getStoreRules(string $storeName, DiscountTemp $tempDiscount)
    {
        switch ($storeName) {
            case 'Lidl':
                return new LidlRules($tempDiscount);
            case 'Maxima':
                return new MaximaRules($tempDiscount);
            case 'Rimi':
                return new RimiRules($tempDiscount);
            case 'Norfa':
                return new NorfaRules($tempDiscount);
            case 'Iki':
                return new IkiRules($tempDiscount);
            default:
                throw new \Exception("No rules found for store: {$storeName}");
        }
    }

    private function assignCategory(DiscountTemp $tempDiscount, Store $store)
    {
        $productName = strtolower($tempDiscount->name);
        $petKeywords = ['šunų', 'ėdalas', 'kačių', 'gyvūnų'];

        foreach ($petKeywords as $keyword) {
            if (mb_strpos($productName, $keyword) !== false) {
                return 619;
            }
        }

        if (empty($tempDiscount->category)) {
            return null;
        }

        $storeCategory = $tempDiscount->category;

        // Try full category exact match
        $mapper = CategoryMapper::where('store', $store->id)
            ->where('store_category', $storeCategory)
            ->orderBy('id', 'asc')
            ->first();

        if ($mapper) {
            return $mapper->category_id;
        }

        // Try LIKE match with full category
        $mapper = CategoryMapper::where('store', $store->id)
            ->where('store_category', 'LIKE', $storeCategory . '%')
            ->orderBy('id', 'asc')
            ->first();

        if ($mapper) {
            return $mapper->category_id;
        }

        $storeCategory = str_replace(['https://iki.lt/'], '', $storeCategory);

        if (str_contains($storeCategory, '/')) {
            $parts = explode('/', $storeCategory);

            // Try 2 parts first (if available)
            if (count($parts) >= 2) {
                $twoParts = $parts[0] . '/' . $parts[1];

                // Try exact match with 2 parts
                $mapper = CategoryMapper::where('store', $store->id)
                    ->where('store_category', $twoParts)
                    ->orderBy('id', 'asc')
                    ->first();

                if ($mapper) {
                    return $mapper->category_id;
                }

                // Try LIKE match with 2 parts
                $mapper = CategoryMapper::where('store', $store->id)
                    ->where('store_category', 'LIKE', $twoParts . '%')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($mapper) {
                    return $mapper->category_id;
                }
            }

            // Try 1 part (first part)
            $firstPart = $parts[0];

            // Try exact match with 1 part
            $mapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', $firstPart)
                ->orderBy('id', 'asc')
                ->first();

            if ($mapper) {
                return $mapper->category_id;
            }

            // Try LIKE match with 1 part
            $mapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', 'LIKE', $firstPart . '%')
                ->orderBy('id', 'asc')
                ->first();

            if ($mapper) {
                return $mapper->category_id;
            }
        }

        return $this->createCategoryAndMapping($tempDiscount->category, $store);
    }

    private function createCategoryAndMapping(string $storeCategory, Store $store)
    {
        CategoryMapper::create([
            'category_id' => null,
            'store_category' => $storeCategory,
            'store' => $store->id,
        ]);

        $this->info("Added unmapped category: {$storeCategory}");

        return null;
    }


}
