<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\NormalizesDiscountDates;

class DiscountTemp extends Model
{
    use HasFactory, NormalizesDiscountDates;

    protected $table = 'discount_temp';

    protected $fillable = [
        'name',
        'category',
        'image_url',
        'product_url',
        'store',
        'original_price',
        'discounted_price',
        'discount_percent',
        'condition',
        'card',
        'start_at',
        'end_at',
        'info',
        'brand',
        'processed',
        'box',
        'page_image_path',
    ];
}
