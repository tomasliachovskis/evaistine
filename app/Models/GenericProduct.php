<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GenericProduct extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'emoji',
        'search_terms',
        'category_id',
        'keyword_page_id',
        'source',
    ];

    protected $casts = [
        'search_terms' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function keywordPage()
    {
        return $this->belongsTo(KeywordPage::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
