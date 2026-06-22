<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreFlyerPage extends Model
{
    protected $fillable = [
        'store_flyer_id',
        'page_number',
        'image_url',
        'sort_order',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function storeFlyer(): BelongsTo
    {
        return $this->belongsTo(StoreFlyer::class);
    }
}
