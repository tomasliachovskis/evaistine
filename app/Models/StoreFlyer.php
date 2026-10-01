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
        'source_id',
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

    // The flyer's own title for meta text and links. Some scraped titles
    // are all caps ("NE MAISTO PREKIŲ PASIŪLYMAI"), which reads as
    // shouting in a snippet.
    public function metaLabel(): string
    {
        $label = $this->title
            ?: $this->catalog_name
            ?: ($this->issue_number ? "Nr. {$this->issue_number}" : 'naujausias leidinys');

        if (preg_match('/\p{L}{4}/u', $label) && mb_strtoupper($label) === $label) {
            $label = preg_replace('/\bnr\./u', 'Nr.', \Illuminate\Support\Str::ucfirst(mb_strtolower($label)));
        }

        return $label;
    }

    // Offers extracted from (or linked to) this flyer.
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
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

    // is_active is a manually-set flag that isn't kept in sync with real
    // validity dates (see StoreFlyerTitleBuilder::toListingArray's own
    // comment) — this derives "still browsable today" from valid_to
    // instead, for the "{Store} leidiniai (N)" badges site-wide.
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->startOfDay()));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderByDesc('valid_from');
    }
}
