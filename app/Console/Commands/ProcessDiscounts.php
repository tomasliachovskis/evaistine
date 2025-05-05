<?php

namespace App\Console\Commands;

use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\Store;
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

            $normalizedOriginalPrice = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $tempDiscount->original_price));
            $normalizedDiscountedPrice = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $tempDiscount->discounted_price));

            $normalizedOriginalPrice = !empty($normalizedOriginalPrice) ? $normalizedOriginalPrice : null;
            $normalizedDiscountedPrice = !empty($normalizedDiscountedPrice) ? $normalizedDiscountedPrice : null;

            $discountPercent = !empty($tempDiscount->discount_percent) ? $tempDiscount->discount_percent : null;
            if (empty($discountPercent) && $normalizedOriginalPrice > 0 && $normalizedDiscountedPrice > 0) {
                $discountPercent = round((($normalizedOriginalPrice - $normalizedDiscountedPrice) / $normalizedOriginalPrice) * 100);
            }

            $startAt = !empty($tempDiscount->start_at) ? $tempDiscount->start_at : now();
            $endAt = !empty($tempDiscount->end_at) ? $tempDiscount->end_at : now();

            $product = Product::firstOrCreate(
                ['name' => $tempDiscount->name],
                [
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
}
