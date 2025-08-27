<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'slug',
        'description',
        'category_id',
        'image_url',
        'seo_title',
        'meta_title',
        'meta_description',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function discount()
    {
        return $this->hasOne(Discount::class);
    }

    public function discountHistories()
    {
        return $this->hasMany(DiscountHistory::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }

    protected static function booted()
    {
        static::updated(function ($product) {
            $cacheKey = "product_slug_{$product->slug}";
            Cache::forget($cacheKey);
        });

        static::deleted(function ($product) {
            $cacheKey = "product_slug_{$product->slug}";
            Cache::forget($cacheKey);
        });
    }
}
