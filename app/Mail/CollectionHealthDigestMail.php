<?php

namespace App\Mail;

use App\Services\CollectionHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// collection-health:digest: last 24 h rows vs added discounts, for the admins.
class CollectionHealthDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array>  $issues  critical + warning issues of CollectionHealthService
     * @param  array<int, array>  $eshop  CollectionHealthService e-shop rows
     * @param  array<string, int>  $counts  severity => count
     */
    public function __construct(public array $issues, public array $eshop, public array $counts)
    {
    }

    public function build(): self
    {
        $critical = $this->counts[CollectionHealthService::CRITICAL];
        $warning = $this->counts[CollectionHealthService::WARNING];
        $status = $critical || $warning
            ? implode(', ', array_filter([$critical ? "{$critical} krit." : null, $warning ? "{$warning} įsp." : null]))
            : 'viskas gerai';

        // Both superakcijos and eVaistine send this to the same admins.
        $site = parse_url(config('app.url'), PHP_URL_HOST);

        return $this->subject("{$site} duomenų surinkimas " . now()->format('m-d') . ": {$status}")
            ->view('emails.collection-health-digest');
    }
}
