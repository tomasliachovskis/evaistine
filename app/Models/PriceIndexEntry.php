<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceIndexEntry extends Model
{
    protected $fillable = ['price_index_snapshot_id', 'item_key', 'item_name', 'store_id', 'product_id', 'price', 'raw_price', 'unit_basis'];

    protected $casts = [
        'price' => 'decimal:2',
        'raw_price' => 'decimal:2',
    ];

    public function snapshot()
    {
        return $this->belongsTo(PriceIndexSnapshot::class, 'price_index_snapshot_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
