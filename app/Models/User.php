<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_admin',
        'oauth_provider',
        'oauth_provider_id',
        'price_watch_unsubscribed_at',
        'preferred_store_slugs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'price_watch_unsubscribed_at' => 'datetime',
        'preferred_store_slugs' => 'array',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }
}
