<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'store_id',
        'original_price',
        'discounted_price',
        'discount_percent',
        'condition',
        'card',
        'product_url',
        'start_at',
        'end_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'card' => 'boolean',
        'original_price' => 'float',
        'discounted_price' => 'float',
        'discount_percent' => 'float',
        'end_at' => 'datetime',
        'start_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeSearchByProductName($query, $searchTerm)
    {
        return $query->whereHas('product', function ($q) use ($searchTerm) {
            $q->where('name', 'like', '%' . $searchTerm . '%');
        });
    }

    protected static function booted()
    {
        static::created(function ($discount) {
            static::clearDiscountsCache();
        });

        static::updated(function ($discount) {
            static::clearDiscountsCache();
        });

        static::deleted(function ($discount) {
            static::clearDiscountsCache();
        });
    }

    private static function clearDiscountsCache()
    {
        Cache::flush();
    }
}
