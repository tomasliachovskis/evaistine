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
        $offers = $discount->product->discounts()->with('store')->get();
        $offerCount = $offers->count();

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
            'min_price' => (float) $discount->product->discounts()->min('discounted_price'),
            'offers' => $offers->map(function($offer) {
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
            'product' => [
                'id' => $discount->product->id,
                'name' => $discount->product->name,
                'slug' => $discount->product->slug,
                'brand' => $discount->product->brand,
                'full_slug' => $discount->product->category->slug . '/' . $discount->product->slug,
                'category_id' => $discount->product->category_id,
                'image_url' => $discount->product->image_url,
//                'meta_title' => $discount->product->name,
//                'meta_description' => $discount->product->description,
//                'seo_title' => $discount->product->name,
//                'seo_description' => $discount->product->description,
                'category' => [
                    'id' => $discount->product->category->id,
                    'name' => $discount->product->category->name,
                    'slug' => $discount->product->category->slug,
                ]
            ],
        ];
    }

    protected function formatListDiscount($discount)
    {
        $offers = $discount->product->discounts()->with('store')->get();
        $offerCount = $offers->count();

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
            'min_price' => (float) $discount->product->discounts()->min('discounted_price'),
            'product' => [
                'id' => $discount->product->id,
                'name' => $discount->product->name,
                'slug' => $discount->product->slug,
                'brand' => $discount->product->brand,
                'full_slug' => $discount->product->category->slug . '/' . $discount->product->slug,
                'category_id' => $discount->product->category_id,
                'image_url' => $discount->product->image_url,
                'category' => [
                    'id' => $discount->product->category->id,
                    'name' => $discount->product->category->name,
                    'slug' => $discount->product->category->slug,
                ]
            ],
        ];
    }
}
