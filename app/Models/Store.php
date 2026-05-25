<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'template',
        'description',
        'slug',
        'seo_title',
        'meta_title',
        'meta_description',
        'flyer_pdf_url',
        'flyer_image_url',
        'flyer_valid_from',
        'flyer_valid_to',
        'flyer_updated_at',
    ];

    protected $casts = [
        'flyer_valid_from' => 'date',
        'flyer_valid_to' => 'date',
        'flyer_updated_at' => 'datetime',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
}
