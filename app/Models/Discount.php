<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Services\MeilisearchService;
use App\Support\NormalizesDiscountDates;

class Discount extends Model
{
    use HasFactory, NormalizesDiscountDates;

    protected $fillable = [
        'product_id',
        'store_id',
        'original_price',
        'discounted_price',
        'discount_percent',
        'condition',
        'info',
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

    protected static function booted()
    {
        static::created(function ($discount) {
            static::syncToMeilisearch($discount);
        });

        static::updated(function ($discount) {
            static::syncToMeilisearch($discount);
        });

        static::deleted(function ($discount) {
            try {
                app(MeilisearchService::class)->deleteDiscount($discount->id);
            } catch (\Exception $e) {
            }
        });
    }

    protected static function syncToMeilisearch($discount)
    {
        try {
            $discount->load(['product.category', 'store']);
            app(MeilisearchService::class)->indexDiscount($discount);
        } catch (\Exception $e) {
        }
    }

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
}
