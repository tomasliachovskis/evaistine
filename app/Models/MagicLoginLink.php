<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLoginLink extends Model
{
    protected $fillable = ['email', 'token', 'code_hash', 'code_attempts', 'expires_at', 'used_at', 'redirect_to'];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
