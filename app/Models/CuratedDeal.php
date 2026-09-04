<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuratedDeal extends Model
{
    protected $fillable = [
        'store_id',
        'scope',
        'category_id',
        'position',
        'discount_id',
        'deal_score',
    ];

    protected $casts = [
        'position' => 'integer',
        'deal_score' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
