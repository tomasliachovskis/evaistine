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

    abstract public function normalizePrice(string $price): ?float;
    abstract public function validate(): bool;
} 