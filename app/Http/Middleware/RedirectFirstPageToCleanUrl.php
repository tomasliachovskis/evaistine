<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

// `?page=1` is the same content as the clean URL. CanonicalUrl already points
// its canonical at the clean URL, but Google still reported these as
// "Duplicate without user-selected canonical" (crawled under the old Next.js
// frontend, which streamed the canonical into <body>). A 301 removes the
// duplicate URL outright instead of relying on the canonical hint.
class RedirectFirstPageToCleanUrl
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->is('livewire/*')) {
            return $next($request);
        }

        $query = $request->query();

        if (!array_key_exists('page', $query) || $query['page'] !== '1') {
            return $next($request);
        }

        unset($query['page']);

        $url = $request->url();

        return redirect()->to($query === [] ? $url : $url.'?'.Arr::query($query), 301);
    }
}
