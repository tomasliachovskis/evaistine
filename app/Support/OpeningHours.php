<?php

namespace App\Support;

use Illuminate\Support\Collection;

// Opening hours for /vaistines/{store}/{city}. Scraped per-day values come
// in many spellings ("08:00-22:00", "8-22", "08:00 – 22:00", "Nedirba", "")
// — this normalizes them for two things: a one-line week summary per
// location (the page used to print 7 near-identical day rows per address,
// which Google read as boilerplate and folded the city page into the chain
// page as a duplicate), and a few real per-city facts computed from them.
class OpeningHours
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const SHORT = [
        'monday' => 'Pr', 'tuesday' => 'An', 'wednesday' => 'Tr', 'thursday' => 'Kt',
        'friday' => 'Pn', 'saturday' => 'Št', 'sunday' => 'Sk',
    ];

    private const MINUTES_PER_DAY = 1440;

    /**
     * One day's value → ['open' => minutes, 'close' => minutes, 'label' => 'HH:MM–HH:MM'],
     * 'closed', or null when it's empty or unparseable. A close at or before
     * the open time (00:00, 24:00) means midnight.
     */
    public static function parseDay(mixed $value): array|string|null
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        if (preg_match('/^nedirb/iu', $value)) {
            return 'closed';
        }

        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?\s*[-–]\s*(\d{1,2})(?::(\d{2}))?$/u', $value, $m)) {
            return null;
        }

        $open = (int) $m[1] * 60 + (int) ($m[2] ?? 0);
        $close = (int) $m[3] * 60 + (int) ($m[4] ?? 0);

        if ($open >= self::MINUTES_PER_DAY || $close > self::MINUTES_PER_DAY) {
            return null;
        }

        if ($close <= $open) {
            $close = self::MINUTES_PER_DAY;
        }

        return ['open' => $open, 'close' => $close, 'label' => self::time($open).'–'.self::time($close)];
    }

    /**
     * "Pr–Pn 08:00–22:00, Št–Sk 09:00–20:00" / "Kasdien 08:00–22:00".
     * Unparseable values are shown as scraped; unknown days are skipped.
     */
    public static function summary(array $hours): ?string
    {
        $groups = [];

        foreach (self::DAYS as $day) {
            $parsed = self::parseDay($hours[$day] ?? null);
            $raw = trim((string) ($hours[$day] ?? ''));

            $value = match (true) {
                is_array($parsed) => $parsed['label'],
                $parsed === 'closed' => 'nedirba',
                $raw !== '' && $raw !== '-' => $raw,
                default => null,
            };

            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['value'] === $value && $groups[$last]['to'] === self::previousDay($day)) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['from' => $day, 'to' => $day, 'value' => $value];
            }
        }

        $groups = array_values(array_filter($groups, fn ($group) => $group['value'] !== null));

        if ($groups === []) {
            return null;
        }

        if (count($groups) === 1 && $groups[0]['from'] === 'monday' && $groups[0]['to'] === 'sunday') {
            return "Kasdien {$groups[0]['value']}";
        }

        return collect($groups)->map(function (array $group) {
            $days = $group['from'] === $group['to']
                ? self::SHORT[$group['from']]
                : self::SHORT[$group['from']].'–'.self::SHORT[$group['to']];

            return "{$days} {$group['value']}";
        })->implode(', ');
    }

    /**
     * Where a city's locations differ, which ones — e.g. which opens
     * earliest, which closes earlier than the rest, which are closed on
     * Sunday — always naming the addresses. A fact every location shares
     * ("all open at 08:00") tells a visitor nothing and is left out, and of
     * the two ends of a range only the smaller group (the exceptions) is
     * named. Empty when the locations don't differ or there's too little
     * parseable data.
     *
     * @return list<string>
     */
    public static function cityFacts(Collection $locations): array
    {
        $total = $locations->count();

        if ($total < 2) {
            return [];
        }

        $weekday = self::parsedFor($locations, 'monday');
        $facts = [];

        if ($weekday->count() >= $total / 2) {
            [$aroundTheClock, $regular] = $weekday->partition(fn ($row) => $row['hours']['open'] === 0 && $row['hours']['close'] === self::MINUTES_PER_DAY);

            if ($aroundTheClock->isNotEmpty()) {
                $facts[] = 'Visą parą dirba: '.self::names($aroundTheClock).'.';
            }

            if ($group = self::exceptions($regular, fn ($row) => $row['hours']['open'], 'min')) {
                $facts[] = 'Anksčiausiai darbo dienomis atsidaro – nuo '.self::time($group['value']).': '.self::names($group['rows']).'.';
            }
            if ($group = self::exceptions($regular, fn ($row) => $row['hours']['open'], 'max')) {
                $facts[] = 'Vėliausiai darbo dienomis atsidaro – tik nuo '.self::time($group['value']).': '.self::names($group['rows']).'.';
            }
            if ($group = self::exceptions($regular, fn ($row) => $row['hours']['close'], 'max')) {
                $facts[] = 'Ilgiausiai darbo dienomis dirba – iki '.self::time($group['value']).': '.self::names($group['rows']).'.';
            }
            if ($group = self::exceptions($regular, fn ($row) => $row['hours']['close'], 'min')) {
                $facts[] = 'Anksčiausiai darbo dienomis uždaro – '.self::time($group['value']).': '.self::names($group['rows']).'.';
            }
        }

        $sunday = self::parsedFor($locations, 'sunday', includeClosed: true);

        if ($sunday->count() >= $total / 2) {
            [$closed, $open] = $sunday->partition(fn ($row) => $row['hours'] === 'closed');

            if ($closed->isNotEmpty() && $closed->count() <= $sunday->count() / 2) {
                $facts[] = 'Sekmadienį nedirba: '.self::names($closed).'.';
            } elseif ($closed->isNotEmpty()) {
                $facts[] = $open->isEmpty()
                    ? 'Sekmadienį nedirba nė viena.'
                    : 'Sekmadienį dirba tik: '.self::names($open, withHours: true).'.';
                $open = collect();
            }

            if ($group = self::exceptions($open, fn ($row) => $row['hours']['close'], 'max')) {
                $facts[] = 'Sekmadienį ilgiausiai dirba – iki '.self::time($group['value']).': '.self::names($group['rows']).'.';
            }
            if ($group = self::exceptions($open, fn ($row) => $row['hours']['close'], 'min')) {
                $facts[] = 'Sekmadienį anksčiausiai uždaro – '.self::time($group['value']).': '.self::names($group['rows']).'.';
            }
        }

        return $facts;
    }

    private static function parsedFor(Collection $locations, string $day, bool $includeClosed = false): Collection
    {
        return $locations
            ->map(fn (array $location) => ['address' => $location['address'], 'hours' => self::parseDay($location['hours'][$day] ?? null)])
            ->filter(fn (array $row) => is_array($row['hours']) || ($includeClosed && $row['hours'] === 'closed'))
            ->values();
    }

    /**
     * The locations at the $end ('min'/'max') of $value — but only when the
     * locations actually differ and that end is at most half of them, i.e.
     * it's an exception worth naming rather than the norm.
     */
    private static function exceptions(Collection $rows, callable $value, string $end): ?array
    {
        if ($rows->count() < 2) {
            return null;
        }

        $values = $rows->map($value);
        if ($values->unique()->count() < 2) {
            return null;
        }

        $target = $end === 'min' ? $values->min() : $values->max();
        $matching = $rows->filter(fn ($row) => $value($row) === $target);

        return $matching->count() <= $rows->count() / 2 ? ['value' => $target, 'rows' => $matching] : null;
    }

    // "A g. 1, B g. 2, C g. 3 ir dar 4" — capped so a big city stays one line.
    private static function names(Collection $rows, bool $withHours = false): string
    {
        $rows = $rows->sortBy('address')->values();
        $shown = $rows->take(3)->map(fn ($row) => $withHours && is_array($row['hours'])
            ? "{$row['address']} ({$row['hours']['label']})"
            : $row['address']);

        return $shown->implode(', ').($rows->count() > 3 ? ' ir dar '.($rows->count() - 3) : '');
    }

    private static function previousDay(string $day): ?string
    {
        $index = array_search($day, self::DAYS, true);

        return $index > 0 ? self::DAYS[$index - 1] : null;
    }

    private static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
