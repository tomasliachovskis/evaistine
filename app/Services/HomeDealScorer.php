<?php

namespace App\Services;

use App\Models\Discount;
use Illuminate\Support\Collection;

class HomeDealScorer
{
    public function score(Discount $discount): float
    {
        $percent = min((float) ($discount->discount_percent ?? 0), 70);
        $original = (float) ($discount->original_price ?? 0);
        $discounted = (float) ($discount->discounted_price ?? 0);
        $savings = max(0, $original - $discounted);

        if (isset($discount->deal_score)) {
            return (float) $discount->deal_score;
        }

        return ($percent * 2) + ($savings * 3);
    }

    public function sortByScore(Collection $discounts): Collection
    {
        return $discounts
            ->sortByDesc(fn (Discount $discount) => [
                $this->score($discount),
                (float) ($discount->discount_percent ?? 0),
            ])
            ->values();
    }
}
