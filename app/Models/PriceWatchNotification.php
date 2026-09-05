<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceWatchNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'product_id',
        'discount_id',
        'notified_price',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'notified_price' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
