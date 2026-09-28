<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'query',
        'ip_address',
        'country_code',
        'total_results',
    ];

    protected $casts = [
        'total_results' => 'integer',
    ];
}
