<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CouponWebsite extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'url',
        'logo_url',
        'description',
    ];

    protected static function booted(): void
    {
        static::saving(function (CouponWebsite $website) {
            if ($website->slug || !$website->name) {
                return;
            }

            $website->slug = Str::slug($website->name);
        });
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'website_id');
    }
}
