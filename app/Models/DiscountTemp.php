<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DiscountTemp extends Model
{
    use HasFactory;

    protected $table = 'discount_history';

    protected $fillable = [
        'name',
        'category',
        'image_url',
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
}
