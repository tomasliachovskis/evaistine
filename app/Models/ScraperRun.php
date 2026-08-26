<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScraperRun extends Model
{
    public const TYPE_SCRAPE = 'scrape';

    public const TYPE_PROCESS = 'process';

    public const TYPE_FLYER_SCRAPE = 'flyer_scrape';

    public const TYPE_HOURS_SCRAPE = 'hours_scrape';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'store',
        'status',
        'step',
        'started_at',
        'finished_at',
        'items_count',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
