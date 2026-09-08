<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceIndexSnapshot extends Model
{
    protected $fillable = ['week_start'];

    protected $casts = [
        'week_start' => 'date',
    ];

    public function entries()
    {
        return $this->hasMany(PriceIndexEntry::class);
    }
}
