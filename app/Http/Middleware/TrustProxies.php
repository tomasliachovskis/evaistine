<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Cloudflare sits in front of every environment (terminates TLS, forwards
     * to the origin over plain HTTP) and its edge IP ranges aren't static, so
     * trusting a fixed IP list isn't practical — '*' is Laravel's documented
     * approach for exactly this. Without it, Laravel never honors
     * X-Forwarded-Proto and treats every request as http://, which broke
     * every absolute URL it generates itself — notably Livewire's own AJAX
     * update endpoint (it posted to http://, which then 503'd), silently
     * breaking every wire:click/wire:model interaction site-wide.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
