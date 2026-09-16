<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PriceWatchController extends Controller
{
    // Reached only via the signed link in the price-watch digest email (see
    // NotifyPriceWatchers) — no login required. GET shows a confirm page so
    // an email client prefetching/scanning the link can't silently opt
    // someone out; POST (the confirm page's own form, posting back to the
    // same signed URL) actually flips the flag. Only silences future
    // digests — favorites/tracking itself is untouched.
    public function unsubscribe(Request $request, User $user)
    {
        if ($request->isMethod('post')) {
            $user->forceFill(['price_watch_unsubscribed_at' => now()])->save();

            return view('price-watch.unsubscribed');
        }

        return view('price-watch.unsubscribe-confirm', ['user' => $user]);
    }
}
