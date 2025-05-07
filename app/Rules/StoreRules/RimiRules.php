<?php

namespace App\Rules\StoreRules;

class RimiRules extends BaseStoreRules
{
    public function validate(): bool
    {
        return !empty($this->tempDiscount->name)
            && !empty($this->tempDiscount->product_url)
            && !empty($this->tempDiscount->category)
            && ($this->tempDiscount->original_price > 0 || $this->tempDiscount->discounted_price > 0);
    }

    public function normalizeProductName()
    {

    }
}
