<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoreCategoryDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'category_id',
        'intro_html',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
