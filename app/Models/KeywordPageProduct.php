<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeywordPageProduct extends Model
{
    protected $fillable = [
        'keyword_page_id',
        'product_id',
        'score',
    ];

    public function keywordPage()
    {
        return $this->belongsTo(KeywordPage::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
