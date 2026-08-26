<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreLocation extends Model
{
    protected $fillable = [
        'store_id',
        'external_id',
        'city',
        'address',
        'slug',
        'lat',
        'lng',
        'phone',
        'hours',
        'is_active',
        'source',
    ];

    protected $casts = [
        'hours' => 'array',
        'lat' => 'float',
        'lng' => 'float',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
