<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// "Naujas leidinys": the new leaflets of a subscriber's stores since the
// last such email (leaflets:notify-subscribers).
class NewLeafletMail extends Mailable
{
    use Queueable, SerializesModels;

    public const UTM = ['utm_source' => 'new_leaflet', 'utm_medium' => 'email', 'utm_campaign' => 'new_leaflet'];

    public static function trackedUrl(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(self::UTM);
    }

    /**
     * @param  list<array{title: string, store_name: string, image: ?string, url: string, dates: ?string}>  $leaflets
     */
    public function __construct(public array $leaflets, public string $settingsUrl)
    {
    }

    public function build(): self
    {
        $first = $this->leaflets[0];
        $subject = count($this->leaflets) === 1
            ? 'Naujas '.$first['store_name'].' leidinys'
            : 'Nauji leidiniai: '.collect($this->leaflets)->pluck('store_name')->unique()->take(3)->implode(', ');

        return $this->subject($subject.' - eVaistinė')->view('emails.new-leaflet');
    }
}
