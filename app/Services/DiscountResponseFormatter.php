<?php

namespace App\Services;

class DiscountResponseFormatter
{
    public function format($discounts)
    {
        if ($discounts instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $discounts->getCollection()->transform(function ($discount) {
                return $this->formatSingleDiscount($discount);
            });
            return $discounts;
        }

        return $discounts->map(function ($discount) {
            return $this->formatSingleDiscount($discount);
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
            'card' => $discount->card,
            'valid_date' => $discount->start_at->format('m d') . ' - ' . $discount->end_at->format('m d'),
            'offers' => $offers->map(function($offer) {
                return [
                    'id' => $offer->id,
                    'store_id' => $offer->store_id,
                    'original_price' => $offer->original_price,
                    'discounted_price' => $offer->discounted_price,
                    'discount_percent' => $offer->discount_percent,
                    'condition' => $offer->condition,
                    'card' => $offer->card,
                    'valid_date' => $offer->start_at->format('m d') . ' - ' . $offer->end_at->format('m d'),
                    'store' => [
                        'id' => $offer->store->id,
                        'name' => $offer->store->name,
                        'slug' => $offer->store->slug
                    ]
                ];
            }),
            'offer_count' => $offerCount,
            'min_price' => (float) $discount->product->discounts()->min('discounted_price'),
            'product' => [
                'id' => $discount->product->id,
                'name' => $discount->product->name,
                'slug' => $discount->product->slug,
                'full_slug' => $discount->product->category->slug . '/' . $discount->product->slug,
                'description' => $discount->product->description,
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
