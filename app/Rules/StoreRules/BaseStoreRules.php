<?php

namespace App\Rules\StoreRules;

use App\Models\DiscountTemp;

abstract class BaseStoreRules
{
    protected DiscountTemp $tempDiscount;

    public function __construct(DiscountTemp $tempDiscount)
    {
        $this->tempDiscount = $tempDiscount;
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

    abstract public function validate(): bool;
}
