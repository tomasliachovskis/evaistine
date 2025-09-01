<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnmappedProduct extends Model
{
    protected $fillable = [
        'name',
        'store',
        'gpt_response',
        'error_message',
        'product_data',
        'mapping_status',
        'attempted_at'
    ];

    protected $casts = [
        'product_data' => 'array',
        'attempted_at' => 'datetime'
    ];

    const STATUS_FAILED = 'failed';
    const STATUS_INVALID_CATEGORY = 'invalid_category';
    const STATUS_NO_RESPONSE = 'no_response';
    const STATUS_PARSE_ERROR = 'parse_error';
}
