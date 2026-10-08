<?php

namespace App\Rules\StoreRules;

use App\Models\DiscountTemp;

// Normalizes and validates one scraped discount_temp row. A single class for
// every store: no store has needed its own price/discount parsing so far.
class DefaultRules
{
    private const MIN_REAL_PRICE = 0.05;

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
        // Gifts with purchase come through at a token price (Benu: 0.01 €,
        // "DOVANA ..."). They aren't for sale and would become every
        // listing's "nuo 0.01 €" cheapest price.
        $price = $this->normalizePrice($this->tempDiscount->discounted_price);
        if ($price > 0 && $price < self::MIN_REAL_PRICE) {
            return false;
        }

        return !empty($this->tempDiscount->name)
            && ($this->normalizePrice($this->tempDiscount->original_price) > 0
                || $this->normalizePrice($this->tempDiscount->discounted_price) > 0
                || !empty($this->tempDiscount->discount_percent)
            );
    }
}
