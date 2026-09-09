<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreFlyer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'store_id',
        'slug',
        'catalog_name',
        'issue_number',
        'title',
        'image_url',
        'thumbnail_url',
        'pdf_url',
        'view_url',
        'valid_from',
        'valid_to',
        'sort_order',
        'is_active',
        'source',
        'processing_status',
        'processing_error',
        'discounts_processed_at',
        'discounts_retry_state',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'discounts_processed_at' => 'datetime',
        'discounts_retry_state' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (StoreFlyer $flyer) {
            if ($flyer->view_url || !$flyer->slug) {
                return;
            }

            $store = $flyer->relationLoaded('store')
                ? $flyer->store
                : Store::find($flyer->store_id);

            if ($store) {
                $flyer->view_url = "/leidinys/{$store->slug}/{$flyer->slug}";
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(StoreFlyerPage::class)->orderBy('sort_order')->orderBy('page_number');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('processing_status', self::STATUS_READY);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderByDesc('valid_from');
    }
}
