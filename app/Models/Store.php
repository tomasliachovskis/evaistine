<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'leaflet_description',
        'slug',
        'seo_title',
        'meta_title',
        'meta_description',
        'faq',
        'flyer_source_url',
        'extract_discounts_from_flyer',
    ];

    protected $casts = [
        'faq' => 'array',
        'extract_discounts_from_flyer' => 'boolean',
    ];

    /**
     * Whether /akcijos/{slug} is a real offers page for this store. Stores
     * without discount extraction only have leaflets — their /akcijos URL
     * 301s to /leidinys/{slug} (AkcijosController::show()) and every link
     * to it should point at the leaflet hub instead.
     */
    public function showsDiscountsPage(): bool
    {
        return (bool) $this->extract_discounts_from_flyer;
    }

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

    public function curatedDeals()
    {
        return $this->hasMany(CuratedDeal::class);
    }

    public function latestActiveFlyer(): ?\App\Models\StoreFlyer
    {
        return $this->flyers()
            ->where('is_active', true)
            ->whereNotNull('valid_from')
            ->whereNotNull('valid_to')
            ->orderByDesc('valid_from')
            ->first();
    }

    public function latestFlyerValidity(): ?array
    {
        $flyer = $this->latestActiveFlyer();

        if (!$flyer) {
            return null;
        }

        return [
            'valid_from' => $flyer->valid_from->format('Y-m-d'),
            'valid_to' => $flyer->valid_to->format('Y-m-d'),
        ];
    }
}
