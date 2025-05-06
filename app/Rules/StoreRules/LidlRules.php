<?php

namespace App\Rules\StoreRules;

class LidlRules extends BaseStoreRules
{
    public function validate(): bool
    {
        return !empty($this->tempDiscount->name)
            && !empty($this->tempDiscount->product_url)
            && ($this->tempDiscount->original_price > 0 || $this->tempDiscount->discounted_price > 0);
    }
}
