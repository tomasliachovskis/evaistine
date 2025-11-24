<?php

namespace App\Services;

class DiscountResponseFormatter
{
    public function format($discounts)
    {
        if ($discounts instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $discounts->getCollection()->transform(function ($discount) {
                return $this->formatListDiscount($discount);
            });

            return $discounts;
        }

        return $discounts->map(function ($discount) {
            return $this->formatSingleDiscount($discount);
        });
    }

    protected function formatSingleDiscount($discount)
    {
        $productDiscounts = $discount->product->discounts()->with('store')->get();
        $productDiscountHistories = $discount->product->discountHistories()->with('store')->orderBy('id', 'desc')->get();
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
            'from_date' => $discount->start_at ? $discount->start_at->format('Y-m-d') : null,
            'to_date' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
            'valid_date' => ($discount->start_at ? $discount->start_at->format('Y-m-d') : '') . ' - ' . ($discount->end_at ? $discount->end_at->format('Y-m-d') : ''),
            'offer_count' => $offerCount,
            'min_price' => (float) $minPrice,
            'offers' => $productDiscounts->map(function($offer) {
                return $this->formatOfferItem($offer);
            }),
            'history' => $productDiscountHistories->map(function($history) {
                return $this->formatOfferItem($history);
            }),
            'product' => $this->formatProductData($discount->product),
        ];
    }

    protected function formatOfferItem($item)
    {
        return [
            'id' => $item->id,
            'store_id' => $item->store_id,
            'original_price' => $item->original_price,
            'discounted_price' => $item->discounted_price,
            'discount_percent' => $item->discount_percent,
            'condition' => $item->condition,
            'info' => $item->info ?? null,
            'card' => $item->card,
            'valid_date' => ($item->start_at ? $item->start_at->format('Y-m-d') : '') . ' - ' . ($item->end_at ? $item->end_at->format('Y-m-d') : ''),
            'from_date' => $item->start_at ? $item->start_at->format('Y-m-d') : null,
            'to_date' => $item->end_at ? $item->end_at->format('Y-m-d') : null,
            'store' => [
                'id' => $item->store->id,
                'name' => $item->store->name,
                'slug' => $item->store->slug
            ]
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
            'valid_date' => ($discount->start_at ? $discount->start_at->format('Y-m-d') : '') . ' - ' . ($discount->end_at ? $discount->end_at->format('Y-m-d') : ''),
            'from_date' => $discount->start_at ? $discount->start_at->format('Y-m-d') : null,
            'to_date' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
            'offers' => [],
            'offer_count' => $offerCount,
            'min_price' => (float) $minPrice,
            'product' => $this->formatProductData($discount->product),
        ];
    }

    public function formatProduct($product)
    {
        $productDiscountHistories = $product->discountHistories()->with('store')->orderBy('id', 'desc')->get();

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
                'history' => $productDiscountHistories->map(function($history) {
                    return $this->formatOfferItem($history);
                }),
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
