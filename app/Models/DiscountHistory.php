<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\NormalizesDiscountDates;

class DiscountHistory extends Model
{
    use HasFactory, NormalizesDiscountDates;

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
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
