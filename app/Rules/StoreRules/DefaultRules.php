<?php

namespace App\Rules\StoreRules;

use App\Models\DiscountTemp;

// Normalizes and validates one scraped discount_temp row. A single class for
// every store: no store has needed its own price/discount parsing so far.
class DefaultRules
{
    protected DiscountTemp $tempDiscount;

    public function __construct(DiscountTemp $tempDiscount)
    {
        $this->tempDiscount = $tempDiscount;
    }

    public function normalizeDiscount(?string $discountPercent)
    {
        $discountPercent = strtolower(trim($discountPercent));
        $discountPercent = preg_replace('/[^0-9-]/', '', $discountPercent);
        $discountPercent = str_replace('-', '', $discountPercent);
        $discountPercent = !empty($discountPercent) ? (int)$discountPercent : null;
        if ($discountPercent < 0) {
            $discountPercent = 0;
        }

        return $discountPercent;
    }

    public function normalizePrice(?string $price): ?float
    {
        if (empty($price)) {
            return null;
        }

        $price = str_replace(['€', ' '], '', $price);
        $price = str_replace(',', '.', $price);
        $price = preg_replace('/[^0-9.]/', '', $price);

        return !empty($price) ? (float) $price : null;
    }

    public function clean(DiscountTemp $discountTemp): DiscountTemp
    {
        return $discountTemp;
    }

    public function validate(): bool
    {
        return !empty($this->tempDiscount->name)
            && ($this->normalizePrice($this->tempDiscount->original_price) > 0
                || $this->normalizePrice($this->tempDiscount->discounted_price) > 0
                || !empty($this->tempDiscount->discount_percent)
            );
    }
}
