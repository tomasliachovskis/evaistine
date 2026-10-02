<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Email notifications without an account: chosen stores plus the
 * "Savaitės santrauka" (weekly-digest:send) and "Naujas leidinys"
 * (leaflets:notify-subscribers) emails. Price-drop emails for followed
 * products stay on the user account (price-watch:notify); the settings page
 * toggles those too when the subscriber is linked to a user.
 */
class EmailSubscriber extends Model
{
    // Used when a subscriber picked no stores.
    public const DEFAULT_STORES = ['maxima', 'norfa', 'lidl', 'rimi', 'iki'];

    protected $fillable = ['email', 'user_id', 'store_slugs', 'wants_weekly', 'wants_new_leaflets', 'token', 'confirmed_at', 'unsubscribed_at', 'weekly_sent_at'];

    protected $casts = [
        'store_slugs' => 'array',
        'wants_weekly' => 'boolean',
        'wants_new_leaflets' => 'boolean',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'weekly_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSendable(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    /**
     * @return list<string>
     */
    public function storeSlugs(): array
    {
        return $this->store_slugs ?: self::DEFAULT_STORES;
    }

    public function settingsUrl(): string
    {
        return url('/pranesimai/'.$this->token);
    }
}
