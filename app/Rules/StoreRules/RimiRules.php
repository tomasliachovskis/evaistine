<?php

namespace App\Rules\StoreRules;

class RimiRules extends BaseStoreRules
{
    public function normalizePrice(string $price): ?float
    {
        if (empty($price)) {
            return null;
        }

        $price = str_replace(['€', ' '], '', $price);
        $price = str_replace(',', '.', $price);
        $price = preg_replace('/[^0-9.]/', '', $price);

        return !empty($price) ? (float) $price : null;
    }

    public function validate(): bool
    {
        return !empty($this->tempDiscount->name) 
            && !empty($this->tempDiscount->product_url)
            && !empty($this->tempDiscount->category)
            && ($this->tempDiscount->original_price > 0 || $this->tempDiscount->discounted_price > 0);
    }
} 