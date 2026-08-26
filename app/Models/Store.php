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
        'faq',
        'flyer_source_url',
    ];

    protected $casts = [
        'faq' => 'array',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }

    public function flyers()
    {
        return $this->hasMany(StoreFlyer::class);
    }

    public function locations()
    {
        return $this->hasMany(StoreLocation::class);
    }

    public function latestFlyerValidity(): ?array
    {
        $flyer = $this->flyers()
            ->where('is_active', true)
            ->whereNotNull('valid_from')
            ->whereNotNull('valid_to')
            ->orderByDesc('valid_from')
            ->first();

        if (!$flyer) {
            return null;
        }

        return [
            'valid_from' => $flyer->valid_from->format('Y-m-d'),
            'valid_to' => $flyer->valid_to->format('Y-m-d'),
        ];
    }
}
