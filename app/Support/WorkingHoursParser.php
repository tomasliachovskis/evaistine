<?php

namespace App\Support;

// Parses nuolaidos.lt's Lithuanian Roman-numeral weekday-range notation
// (e.g. ["I-V 08:00-20:00", "VI 08:00-20:00", "VII 08:00-17:00"]) into a
// normalized per-day map. I=Monday...VII=Sunday. A day not mentioned in the
// source array is treated as closed (null).
class WorkingHoursParser
{
    private const DAYS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

    private const DAY_NAMES = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public static function parse(?array $workTimes): array
    {
        $hours = array_fill_keys(self::DAY_NAMES, null);

        foreach ($workTimes ?? [] as $entry) {
            $entry = trim((string) $entry);

            if (!preg_match('/^([IVX]+)(?:-([IVX]+))?\s+(.+)$/u', $entry, $matches)) {
                continue;
            }

            $fromIndex = array_search($matches[1], self::DAYS, true);
            $toIndex = array_search($matches[2] !== '' ? $matches[2] : $matches[1], self::DAYS, true);

            if ($fromIndex === false || $toIndex === false || $fromIndex > $toIndex) {
                continue;
            }

            $timeRange = trim($matches[3]);

            for ($i = $fromIndex; $i <= $toIndex; $i++) {
                $hours[self::DAY_NAMES[$i]] = $timeRange;
            }
        }

        return $hours;
    }
}
