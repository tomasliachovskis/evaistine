<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Written by the browser in plain text (see App\Support\MyStores).
        \App\Support\MyStores::COOKIE,
        \App\Support\MyStores::SHOW_ALL_COOKIE,
    ];
}
