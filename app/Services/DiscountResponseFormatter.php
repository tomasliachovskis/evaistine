<?php

namespace App\Services;

class DiscountResponseFormatter
{
    public function format($discounts)
    {
        if ($discounts instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $discounts->getCollection()->transform(function ($discount) {
                return $this?->formatListDiscount($discount);
            });

            return $discounts;
        }

        return $discounts->map(function ($discount) {
            return $this?->formatSingleDiscount($discount);
        });
    }

    protected function formatSingleDiscount($discount)
    {
        $productDiscounts = $discount->product->discounts()->with('store')->get();
        $offerCount = $productDiscounts->count();
        $minPrice = $productDiscounts->min('discounted_price');

        return [
            'id' => $discount->id,
            'store_id' => $discount->store_id,
            'original_price' => $discount->original_price,
            'discounted_price' => $discount->discounted_price,
            'discount_percent' => $discount->discount_percent,
            'condition' => $discount->condition,
            'info' => $discount->info,
            'card' => $discount->card,
            'from_date' => $discount->start_at?->format('Y-m-d'),
            'to_date' => $discount->end_at?->format('Y-m-d'),
            'valid_date' => $discount->start_at?->format('Y-m-d') . ' - ' . $discount->end_at?->format('Y-m-d'),
            'offer_count' => $offerCount,
            'min_price' => (float) $minPrice,
            'offers' => $productDiscounts->map(function($offer) {
                return [
                    'id' => $offer->id,
                    'store_id' => $offer->store_id,
                    'original_price' => $offer->original_price,
                    'discounted_price' => $offer->discounted_price,
                    'discount_percent' => $offer->discount_percent,
                    'condition' => $offer->condition,
                    'info' => $offer->info,
                    'card' => $offer->card,
                    'valid_date' => $offer->start_at?->format('Y-m-d') . ' - ' . $offer->end_at?->format('Y-m-d'),
                    'from_date' => $offer->start_at?->format('Y-m-d'),
                    'to_date' => $offer->end_at?->format('Y-m-d'),
                    'store' => [
                        'id' => $offer->store->id,
                        'name' => $offer->store->name,
                        'slug' => $offer->store->slug
                    ]
                ];
            }),
            'product' => $this->formatProductData($discount->product),
        ];
    }

    protected function formatListDiscount($discount)
    {
        $productDiscounts = $discount->product->discounts()->with('store')->get();
        $offerCount = $productDiscounts->count();
        $minPrice = $productDiscounts->min('discounted_price');

        return [
            'id' => $discount->id,
            'store_id' => $discount->store_id,
            'original_price' => $discount->original_price,
            'discounted_price' => $discount->discounted_price,
            'discount_percent' => $discount->discount_percent,
            'condition' => $discount->condition,
            'info' => $discount->info,
            'card' => $discount->card,
            'valid_date' => $discount->start_at?->format('Y-m-d') . ' - ' . $discount->end_at?->format('Y-m-d'),
            'from_date' => $discount->start_at?->format('Y-m-d'),
            'to_date' => $discount->end_at?->format('Y-m-d'),
            'offers' => [],
            'offer_count' => $offerCount,
            'min_price' => (float) $minPrice,
            'product' => $this->formatProductData($discount->product),
        ];
    }

    public function formatProduct($product)
    {
        return collect([
            [
                'id' => null,
                'store_id' => null,
                'original_price' => null,
                'discounted_price' => null,
                'discount_percent' => null,
                'condition' => null,
                'info' => null,
                'card' => null,
                'from_date' => null,
                'to_date' => null,
                'valid_date' => null,
                'offer_count' => 0,
                'min_price' => 0,
                'offers' => [],
                'product' => $this->formatProductData($product),
            ]
        ]);
    }

    protected function formatProductData($product)
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'full_slug' => $product->category ? $product->category->slug . '/' . $product->slug : $product->slug,
            'category_id' => $product->category_id,
            'image_url' => $product->image_url,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
        ];
    }
}
