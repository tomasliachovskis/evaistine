<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductDuplicateQueryService
{
    public function getDuplicatePairs(): Collection
    {
        $path = database_path('sql/find_product_duplicates.sql');
        $sql = file_get_contents($path);

        return collect(DB::select($sql));
    }
}
