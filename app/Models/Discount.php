<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_url',
        'store_id',
        'original_price',
        'discounted_price',
        'discount_percent',
        'condition',
        'card',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'card' => 'boolean',
        'original_price' => 'float',
        'discounted_price' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeSearchByProductName($query, $name)
    {
        return $query->whereHas('product', function($q) use ($name) {
            $q->where('name', 'like', '%' . $name . '%');
        });
    }
}
