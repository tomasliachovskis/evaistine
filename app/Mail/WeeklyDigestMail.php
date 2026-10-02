<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

// The Thursday email: new leaflets and the best offers of the subscriber's
// stores (content from App\Services\WeeklyDigestBuilder).
class WeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    // GA attribution, same idea as PriceWatchDiscountMail::UTM.
    public const UTM = ['utm_source' => 'weekly', 'utm_medium' => 'email', 'utm_campaign' => 'weekly_digest'];

    public static function trackedUrl(string $url, string $content): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(self::UTM + ['utm_content' => $content]);
    }

    /**
     * @param  list<array>  $leaflets
     */
    public function __construct(
        public Collection $stores,
        public array $leaflets,
        public Collection $offers,
        public string $allOffersUrl,
        public string $settingsUrl,
    ) {
    }

    public function build(): self
    {
        $names = $this->stores->pluck('name')->take(3)->implode(', ');

        return $this->subject('Šios savaitės akcijos: '.$names.' - SuperAkcijos')
            ->view('emails.weekly-digest');
    }
}
