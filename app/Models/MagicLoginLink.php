<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLoginLink extends Model
{
    protected $fillable = ['email', 'token', 'expires_at', 'used_at', 'redirect_to'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
