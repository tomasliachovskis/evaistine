<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFact extends Model
{

    protected $fillable = [
        'store_id',
        'product_url',
        'url_hash',
        'product_id',
        'page_name',
        'page_category',
        'source_text',
        'attributes',
        'source_hash',
        'facts',
        'extracted_with',
        'fetched_at',
        'extracted_at',
        'pushed_at',
    ];

    protected $casts = [
        'attributes' => 'array',
        'facts' => 'array',
        'fetched_at' => 'datetime',
        'extracted_at' => 'datetime',
        'pushed_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
