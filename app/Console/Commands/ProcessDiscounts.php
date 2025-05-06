<?php

namespace App\Console\Commands;

use App\Models\Discount;
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

class ProcessDiscounts extends Command
{
    protected $signature = 'discounts:process';
    protected $description = 'Process new discounts from discount_temp table';

    public function handle()
    {
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

            $product = Product::firstOrCreate(
                ['slug' => Str::slug($tempDiscount->name)],
                [
                    'name' => $tempDiscount->name,
                    'slug' => Str::slug($tempDiscount->name),
                    'description' => '',
                    'category_id' => null,
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
}
