<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_CODE = 'code';

    public const TYPE_DEAL = 'deal';

    protected $fillable = [
        'website_id',
        'category_id',
        'type',
        'title',
        'slug',
        'code',
        'description',
        'terms',
        'discount_label',
        'discount_percent',
        'target_url',
        'is_exclusive',
        'is_verified',
        'editor_tip',
        'usage_count',
        'valid_from',
        'valid_until',
        'is_active',
        'sort_order',
        'source',
    ];

    protected $casts = [
        'discount_percent' => 'float',
        'is_exclusive' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'sort_order' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            if ($coupon->slug || !$coupon->title) {
                return;
            }

            $website = $coupon->relationLoaded('website')
                ? $coupon->website
                : CouponWebsite::find($coupon->website_id);

            $base = Str::slug(($website?->slug ? "{$website->slug}-" : '').$coupon->title);
            $slug = $base;
            $suffix = 1;

            while (static::where('slug', $slug)->where('id', '!=', $coupon->id ?? 0)->exists()) {
                $suffix++;
                $slug = "{$base}-{$suffix}";
            }

            $coupon->slug = $slug;
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(CouponWebsite::class, 'website_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // is_active is a manual kill-switch, not a validity check — mirrors
    // StoreFlyer::scopeCurrentlyValid's "don't trust the flag alone" logic.
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->startOfDay()));
    }

    public function scopeCode(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CODE);
    }

    public function scopeDeal(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEAL);
    }

    public function scopeExclusiveOnly(Builder $query): Builder
    {
        return $query->where('is_exclusive', true);
    }

    public function linkUrl(): ?string
    {
        return $this->target_url ?: $this->website?->url;
    }
}
