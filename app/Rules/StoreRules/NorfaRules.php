<?php

namespace App\Rules\StoreRules;

class NorfaRules extends BaseStoreRules
{
    public function validate(): bool
    {
        return !empty($this->tempDiscount->name)
            && ($this->tempDiscount->original_price > 0 || $this->tempDiscount->discounted_price > 0);
    }
}
