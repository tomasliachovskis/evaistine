<?php

namespace App\Support;

use Carbon\Carbon;

// App locale is 'en' (config/app.php), so Carbon::translatedFormat() would
// render English month names — ported from discount/src/lib/blog-utils.ts's
// LT_MONTHS_SHORT map instead of switching the whole app's locale.
class LithuanianDate
{
    private const MONTHS_SHORT = [
        'saus.', 'vas.', 'kov.', 'bal.', 'geg.', 'birž.',
        'liep.', 'rugp.', 'rugs.', 'spal.', 'lapkr.', 'gruod.',
    ];

    // Accusative case ("[during] September" -> "rugsėjį") — for phrases
    // like "akcija rugsėjį", not the nominative "rugsėjis" a plain month
    // name would give.
    private const MONTHS_ACCUSATIVE = [
        'sausį', 'vasarį', 'kovą', 'balandį', 'gegužę', 'birželį',
        'liepą', 'rugpjūtį', 'rugsėjį', 'spalį', 'lapkritį', 'gruodį',
    ];

    public static function shortMonth(Carbon $date): string
    {
        return self::MONTHS_SHORT[$date->month - 1];
    }

    public static function monthAccusative(Carbon $date): string
    {
        return self::MONTHS_ACCUSATIVE[$date->month - 1];
    }
}
