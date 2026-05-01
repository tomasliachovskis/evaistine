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
        'image_from_flyer',
        'seo_title',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'image_from_flyer' => 'boolean',
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
}
