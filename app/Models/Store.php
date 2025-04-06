<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'url',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
