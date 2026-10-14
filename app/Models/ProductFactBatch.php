<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// One Gemini Batch API job of product-facts:batch-submit (docs/product-facts.md).
class ProductFactBatch extends Model
{
    /** Gemini job states after which nothing changes any more. */
    public const FINAL_STATES = [
        'JOB_STATE_SUCCEEDED',
        'JOB_STATE_FAILED',
        'JOB_STATE_CANCELLED',
        'JOB_STATE_EXPIRED',
    ];

    protected $fillable = [
        'name',
        'model',
        'state',
        'row_count',
        'input_file',
        'input_tokens',
        'output_tokens',
        'error',
        'submitted_at',
        'finished_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function facts()
    {
        return $this->hasMany(ProductFact::class, 'batch_id');
    }
}
