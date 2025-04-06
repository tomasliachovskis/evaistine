<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DiscountHistory extends Model
{
    use HasFactory;

    protected $table = 'discount_history';

    protected $fillable = [
        'product_id',
        'original_price',
        'discounted_price',
        'product_url',
        'coupon_code',
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
}
