<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreFlyer;
use Carbon\Carbon;
use Illuminate\Support\Str;

class StoreFlyerSlugBuilder
{
    public function build(
        Store $store,
        string $title,
        string $validFrom,
        string $validTo,
        ?int $excludeFlyerId = null
    ): string {
        $base = Str::slug($title)
            . '-'
            . Carbon::parse($validFrom)->format('Ymd')
            . '-'
            . Carbon::parse($validTo)->format('Ymd');

        $slug = $base;
        $counter = 2;

        while ($this->slugExists($store->id, $slug, $excludeFlyerId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(int $storeId, string $slug, ?int $excludeFlyerId): bool
    {
        return StoreFlyer::query()
            ->where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($excludeFlyerId, fn ($query) => $query->where('id', '!=', $excludeFlyerId))
            ->exists();
    }
}
