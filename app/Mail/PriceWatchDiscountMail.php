<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

// One digest email per user per price-watch:notify run, listing every
// favorited product currently on an active Discount that hasn't been
// emailed to this user in the last 5 days (see NotifyPriceWatchers'
// RENOTIFY_COOLDOWN_DAYS / PriceWatchNotification).
class PriceWatchDiscountMail extends Mailable
{
    use Queueable, SerializesModels;

    // GA attribution: email clients mostly send no referrer, so without
    // these every click from this email lands in GA as Direct / (none).
    // utm_content tells a product-row click apart from the favorites CTA.
    public const UTM = ['utm_source' => 'price_watch', 'utm_medium' => 'email', 'utm_campaign' => 'price_drop'];

    public static function trackedUrl(string $url, string $content): string
    {
        $query = http_build_query(self::UTM + ['utm_content' => $content]);

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }

    // $productGroups: Collection<Collection<Discount>> — one inner collection
    // per distinct product, its Discounts sorted cheapest-first (product/
    // store eager-loaded), so a product on sale at several stores at once
    // lists every store instead of repeating the product per store.
    // $favoritesUrl: one magic auto-login link (see NotifyPriceWatchers) that
    // logs the user in and lands them on /favorites — viewing an individual
    // product needs no login, so only this one shared link does.
    // $totalSavings: same "Galite sutaupyti dabar" figure shown on /favorites.
    // $unsubscribeUrl: signed, no-login-required link (see PriceWatchController)
    // that opts the user out of future price-watch digests.
    public function __construct(public Collection $productGroups, public string $favoritesUrl, public float $totalSavings, public string $unsubscribeUrl)
    {
    }

    public function build(): self
    {
        $subject = $this->productGroups->count() === 1
            ? 'Atpigo prekė, kurią sekate - SuperAkcijos'
            : 'Atpigo ' . $this->productGroups->count() . ' prekės, kurias sekate - SuperAkcijos';

        return $this->subject($subject)
            ->view('emails.price-watch-discount', [
                'productGroups' => $this->productGroups,
                'favoritesUrl' => $this->favoritesUrl,
                'totalSavings' => $this->totalSavings,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
    }
}
