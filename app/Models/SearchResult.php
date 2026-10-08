<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class SearchResult extends Model
{
    use HasFactory;
    use MassPrunable;

    protected $fillable = [
        'query',
        'ip_address',
        'country_code',
        'total_results',
    ];

    protected $casts = [
        'total_results' => 'integer',
    ];

    // Rows carry the searcher's IP; the privacy policy promises they're
    // kept for 12 months (pruned daily by model:prune in the scheduler).
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYear());
    }
}
