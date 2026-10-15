<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreFlyer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Read-only health check of discount + leaflet collection, built only from
// what actually landed in this DB (discount_temp, discounts, store_flyers,
// failed_jobs, category_mappers). scraper_runs is deliberately not used:
// scrapers run on a local machine and POST rows to prod, so prod never sees
// their runs, and the local runs' items_count is always 0. A failed scrape
// shows up here as its consequence: no new rows, a count drop, or a stale
// leaflet. Rules and thresholds: docs/collection-health.md.
class CollectionHealthService
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const INFO = 'info';

    // Sparkline window, today included.
    public const DAYS = 15;

    private const STALE_HOURS = 30;

    private const UNPROCESSED_MINUTES = 30;

    private const DROP_RATIO = -0.5;

    private const JUMP_RATIO = 2.0;

    private const FLYER_LOOKBACK_DAYS = 90;

    private const FLYER_STUCK_MINUTES = 60;

    private const GEMINI_ATTEMPTS_WARN = 3;

    private const FAILED_JOBS_RECENT_DAYS = 7;

    // Share of the last 24 h rows that discounts:process can't use (no
    // date, already expired, no price) before it's an issue, and the
    // minimum rows for the share to mean anything.
    private const BAD_ROWS_RATIO = 0.2;

    private const BAD_ROWS_MIN = 10;

    // Active e-shop discounts below this share of the products that arrived
    // in the last 24 h: rows come in but don't become discounts.
    private const ADDED_RATIO = 0.5;

    // A date discounts:process can read: discount_temp.start_at/end_at are
    // varchar, so a scraper's "Invalid Date" or "" lands as is.
    private const ISO_DATE = "'^[0-9]{4}-[0-9]{2}-[0-9]{2}'";

    public function report(): array
    {
        $today = Carbon::today();
        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $ago) => $today->copy()->subDays($ago)->toDateString())->all();

        $eshop = $this->eshopRows($days);
        $flyers = $this->flyerRows();
        $globals = $this->globals();

        $issues = collect()
            ->merge($this->eshopIssues($eshop))
            ->merge($this->skippedRunIssues($eshop, $days))
            ->merge($flyers['issues'])
            ->merge($globals['issues']);

        $issues = $this->groupIssues($issues)
            ->sortBy(fn (array $issue) => [self::severityRank($issue['severity']), $issue['store']])
            ->values()
            ->all();

        $latestRow = collect($eshop)->pluck('last_at')->filter()->max();

        return [
            'generated_at' => now(),
            'days' => $days,
            'issues' => $issues,
            'counts' => [
                self::CRITICAL => collect($issues)->where('severity', self::CRITICAL)->count(),
                self::WARNING => collect($issues)->where('severity', self::WARNING)->count(),
            ],
            'latest_eshop_row_at' => $latestRow,
            'unprocessed' => $globals['unprocessed'],
            'unmapped_categories' => $globals['unmapped_categories'],
            'failed_jobs' => $globals['failed_jobs'],
            'eshop' => $eshop,
            'flyers' => $flyers['rows'],
        ];
    }

    public function issues(): array
    {
        return $this->report()['issues'];
    }

    // One row per e-shop scraper store (config('scrapers')): unique products
    // per day for the last DAYS days. discount_temp also holds rows Gemini
    // extracted from leaflets (store_flyer_id / page_image_path set), which
    // are left out here. Unique product_url, not row count: two runs on the
    // same day double the rows. Norfa sends no product_url, so name is the
    // fallback key.
    private function eshopRows(array $days): array
    {
        $names = array_keys(config('scrapers'));
        $since = Carbon::parse($days[0])->startOfDay();

        $daily = DB::table('discount_temp')
            ->selectRaw('LOWER(store) AS store_key, DATE(created_at) AS day')
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(product_url, ''), name)) AS products")
            ->selectRaw('MAX(created_at) AS last_at')
            ->where('created_at', '>=', $since)
            ->whereNull('store_flyer_id')
            ->whereNull('page_image_path')
            ->groupBy('store_key', 'day')
            ->get()
            ->groupBy(fn ($row) => mb_strtolower($row->store_key));

        $unprocessed = DB::table('discount_temp')
            ->selectRaw('LOWER(store) AS store_key, COUNT(*) AS total, MIN(created_at) AS oldest')
            ->where('processed', false)
            ->groupBy('store_key')
            ->get()
            ->keyBy(fn ($row) => mb_strtolower($row->store_key));

        $stores = $this->storesByName($names);
        $lastDay = $this->lastDayRows();

        $created = DB::table('discounts')
            ->selectRaw('store_id, COUNT(*) AS total')
            ->whereNull('store_flyer_id')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('store_id')
            ->pluck('total', 'store_id');

        $active = DB::table('discounts')
            ->selectRaw('store_id, COUNT(*) AS total')
            ->whereNull('store_flyer_id')
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay()))
            ->groupBy('store_id')
            ->pluck('total', 'store_id');

        return collect($names)->map(function (string $name) use ($days, $daily, $unprocessed, $stores, $active, $lastDay, $created) {
            $key = mb_strtolower($name);
            $byDay = ($daily[$key] ?? collect())->keyBy('day');
            $series = array_map(fn (string $day) => (int) ($byDay[$day]->products ?? 0), $days);
            $store = $stores[$key] ?? null;
            $lastAt = ($daily[$key] ?? collect())->max('last_at');

            return [
                'store' => $name,
                'store_id' => $store?->id,
                'last_at' => $lastAt ? Carbon::parse($lastAt) : null,
                'series' => $series,
                ...$this->countAnomaly($series, $days),
                'unprocessed' => (int) ($unprocessed[$key]->total ?? 0),
                'unprocessed_oldest' => isset($unprocessed[$key]) ? Carbon::parse($unprocessed[$key]->oldest) : null,
                'active' => $store ? (int) ($active[$store->id] ?? 0) : null,
                'last_24h' => [
                    'rows' => (int) ($lastDay[$key]->total_rows ?? 0),
                    'products' => (int) ($lastDay[$key]->products ?? 0),
                    'no_dates' => (int) ($lastDay[$key]->no_dates ?? 0),
                    'expired' => (int) ($lastDay[$key]->expired ?? 0),
                    'no_price' => (int) ($lastDay[$key]->no_price ?? 0),
                    'created' => $store ? (int) ($created[$store->id] ?? 0) : null,
                ],
            ];
        })->all();
    }

    // What the e-shop scrapers sent in the last 24 h, and how many of those
    // rows discounts:process will skip without a trace: no readable date
    // (Norfa, 2026-10: "Invalid Date" on every row after a site format
    // change, nothing flagged it), an end date already past, or no price.
    private function lastDayRows(): Collection
    {
        $iso = self::ISO_DATE;
        $noPrice = "discounted_price IS NULL OR TRIM(discounted_price) = '' OR REPLACE(REPLACE(REPLACE(discounted_price, '€', ''), ',', '.'), ' ', '') + 0 <= 0";

        return DB::table('discount_temp')
            ->selectRaw('LOWER(store) AS store_key, COUNT(*) AS total_rows')
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(product_url, ''), name)) AS products")
            ->selectRaw("SUM((start_at IS NULL OR start_at NOT REGEXP {$iso}) AND (end_at IS NULL OR end_at NOT REGEXP {$iso})) AS no_dates")
            ->selectRaw("SUM(end_at REGEXP {$iso} AND LEFT(end_at, 10) < ?) AS expired", [Carbon::today()->toDateString()])
            ->selectRaw("SUM({$noPrice}) AS no_price")
            ->where('created_at', '>=', now()->subDay())
            ->whereNull('store_flyer_id')
            ->whereNull('page_image_path')
            ->groupBy('store_key')
            ->get()
            ->keyBy(fn ($row) => mb_strtolower($row->store_key));
    }

    // Latest day with data (today or yesterday, today's run may not have
    // happened yet) against the baseline: median of non-empty days 8-14 days
    // ago. A 14-day median "absorbs" a gradual drop (Lidl, 2026-10: 14-day
    // median 95 showed only -28 %, the week-ago baseline 149 showed -54 %).
    private function countAnomaly(array $series, array $days): array
    {
        $last = count($series) - 1;
        $latestIndex = $series[$last] > 0 ? $last : ($series[$last - 1] > 0 ? $last - 1 : null);
        $baseline = $this->median(array_slice($series, 0, 7));
        $latest = $latestIndex !== null ? $series[$latestIndex] : null;

        $delta = ($latest !== null && $baseline) ? $latest / $baseline - 1 : null;
        $isAnomaly = $delta !== null && ($delta < self::DROP_RATIO || $delta > self::JUMP_RATIO);

        // Walk back over non-empty days while they stay anomalous, to say
        // since when the count has been off.
        $anomalySince = null;
        if ($isAnomaly) {
            for ($i = $latestIndex; $i >= 7; $i--) {
                if ($series[$i] === 0) {
                    continue;
                }
                $d = $series[$i] / $baseline - 1;
                if ($d >= self::DROP_RATIO && $d <= self::JUMP_RATIO) {
                    break;
                }
                $anomalySince = $days[$i];
            }
        }

        $nonEmpty = array_values(array_filter(array_slice($series, 1), fn (int $n) => $n > 0));

        return [
            'latest' => $latest,
            'latest_day' => $latestIndex !== null ? $days[$latestIndex] : null,
            'baseline' => $baseline,
            'delta' => $delta,
            'is_anomaly' => $isAnomaly,
            'anomaly_since' => $anomalySince,
            'min' => $nonEmpty ? min($nonEmpty) : null,
            'max' => $nonEmpty ? max($nonEmpty) : null,
        ];
    }

    private function eshopIssues(array $rows): Collection
    {
        $issues = collect();
        $staleBefore = now()->subHours(self::STALE_HOURS);

        foreach ($rows as $row) {
            $link = $row['store_id'] ? ['store', $row['store_id']] : null;

            if (!$row['last_at'] || $row['last_at']->lt($staleBefore)) {
                $issues->push($this->issue(
                    self::CRITICAL,
                    $row['store'],
                    $row['last_at']
                        ? 'E-shop eilučių neatėjo daugiau nei ' . self::STALE_HOURS . ' val. (paskutinė ' . $row['last_at']->format('m-d H:i') . ')'
                        : 'E-shop eilučių neatėjo per ' . self::DAYS . ' d.',
                    $row['last_at']?->format('m-d'),
                    $link,
                ));
            }

            // Also when rows stopped coming: the site shows no deals for the
            // store at all, which matters more than why.
            if ($row['active'] === 0) {
                $issues->push($this->issue(
                    self::CRITICAL,
                    $row['store'],
                    $row['last_24h']['rows'] > 0
                        ? 'Duomenys ateina, bet 0 aktyvių e-shop nuolaidų (ar suveikė discounts:process?)'
                        : 'Nėra nė vienos aktyvios e-shop nuolaidos',
                    null,
                    $link,
                ));
            }

            if ($row['is_anomaly']) {
                $message = sprintf(
                    'E-shop: %d unikalių prekių (%s) vs bazė %d prieš savaitę (%s)',
                    $row['latest'],
                    Carbon::parse($row['latest_day'])->format('m-d'),
                    $row['baseline'],
                    self::formatDelta($row['delta']),
                );
                if ($row['min'] && $row['max'] / $row['min'] >= 5) {
                    $message .= ", per 14 d. svyruoja {$row['min']} … {$row['max']}";
                }

                $issues->push($this->issue(self::WARNING, $row['store'], $message, $row['anomaly_since'] ? Carbon::parse($row['anomaly_since'])->format('m-d') : null, $link));
            }

            $issues = $issues->merge($this->badRowIssues($row, $link));

            if ($row['unprocessed_oldest'] && $row['unprocessed_oldest']->lt(now()->subMinutes(self::UNPROCESSED_MINUTES))) {
                $issues->push($this->issue(
                    self::WARNING,
                    $row['store'],
                    "{$row['unprocessed']} neapdorotų discount_temp eilučių, seniausia " . $row['unprocessed_oldest']->format('m-d H:i'),
                    $row['unprocessed_oldest']->format('m-d'),
                    $link,
                ));
            }
        }

        return $issues;
    }

    private function badRowIssues(array $row, ?array $link): Collection
    {
        $issues = collect();
        $last = $row['last_24h'];
        if ($last['rows'] < self::BAD_ROWS_MIN) {
            return $issues;
        }

        $checks = [
            ['no_dates', self::CRITICAL, 'be galiojimo datų: discounts:process jas praleidžia (pasikeitė datų formatas svetainėje?)'],
            ['no_price', self::CRITICAL, 'be kainos: discounts:process jas praleidžia (pasikeitė kainos selektorius?)'],
            ['expired', self::WARNING, 'jau pasibaigusios (end_at < šiandien): blogi metai ar mėnuo?'],
        ];

        foreach ($checks as [$field, $severity, $text]) {
            if ($last[$field] / $last['rows'] > self::BAD_ROWS_RATIO) {
                $flagged = true;
                $issues->push($this->issue(
                    $severity,
                    $row['store'],
                    sprintf('Per 24 val. %d iš %d e-shop eilučių %s', $last[$field], $last['rows'], $text),
                    null,
                    $link,
                ));
            }
        }

        // The generic catch for any other silent skip (unmapped category,
        // validate()). Not repeated when a cause above already explains it,
        // and 0 active is its own critical issue.
        if (empty($flagged) && $row['active'] && $row['active'] < $last['products'] * self::ADDED_RATIO) {
            $issues->push($this->issue(
                self::WARNING,
                $row['store'],
                sprintf('Per 24 val. atėjo %d prekių, bet aktyvių e-shop nuolaidų tik %d (%d %%): eilutės nepridedamos (nesusietos kategorijos?)', $last['products'], $row['active'], round($row['active'] / $last['products'] * 100)),
                null,
                $link,
            ));
        }

        return $issues;
    }

    // A day where under half the e-shop stores sent anything means the whole
    // scheduled run was skipped (the local machine was asleep at 03:00), not
    // that one scraper broke. Only days before the latest normal day count:
    // a gap still running up to today is the per-store "no rows for 30 h"
    // issue already, and today's run may still come.
    private function skippedRunIssues(array $rows, array $days): Collection
    {
        $storeCount = count($rows);
        $withData = array_map(
            fn (int $i) => collect($rows)->filter(fn (array $row) => $row['series'][$i] > 0)->count(),
            array_keys($days),
        );
        $lastNormal = collect($withData)->filter(fn (int $n) => $n >= $storeCount / 2)->keys()->last();
        $skipped = [];

        foreach (array_slice($days, 0, $lastNormal ?? 0) as $i => $day) {
            if ($withData[$i] < $storeCount / 2) {
                $skipped[$day] = $withData[$i];
            }
        }

        if (!$skipped) {
            return collect();
        }

        $latestDay = array_key_last($skipped);
        $others = collect(array_keys($skipped))->reject(fn (string $d) => $d === $latestDay)->map(fn (string $d) => Carbon::parse($d)->format('m-d'));

        $message = sprintf('%s duomenis atsiuntė tik %d iš %d e-shop parduotuvių (praleistas visas run\'as)', Carbon::parse($latestDay)->format('m-d'), $skipped[$latestDay], $storeCount);
        if ($others->isNotEmpty()) {
            $message .= '. Taip pat ' . $others->implode(', ');
        }

        $severity = Carbon::parse($latestDay)->gte(Carbon::today()->subDays(2)) ? self::CRITICAL : self::INFO;

        return collect([$this->issue($severity, 'visos e-shop', $message, Carbon::parse($latestDay)->format('m-d'))]);
    }

    // One row per leaflet store (config('flyer_scrapers')). The "current"
    // leaflet is the one with the latest valid_to. Leaflets without valid_to
    // are treated as valid forever by the site, so they are flagged too.
    private function flyerRows(): array
    {
        $names = array_keys(config('flyer_scrapers'));
        $stores = $this->storesByName($names);
        $today = Carbon::today();
        $issues = collect();

        $flyers = StoreFlyer::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where(fn ($q) => $q
                ->where('created_at', '>=', now()->subDays(self::FLYER_LOOKBACK_DAYS))
                ->orWhereNull('valid_to')
                ->orWhere('valid_to', '>=', $today))
            ->get(['id', 'store_id', 'valid_from', 'valid_to', 'processing_status', 'processing_error', 'discounts_processed_at', 'discounts_retry_state', 'created_at', 'updated_at'])
            ->groupBy('store_id');

        $linked = DB::table('discounts')
            ->selectRaw('store_flyer_id, COUNT(*) AS total')
            ->whereIn('store_flyer_id', $flyers->flatten()->pluck('id'))
            ->groupBy('store_flyer_id')
            ->pluck('total', 'store_flyer_id');

        $activeByStore = DB::table('discounts')
            ->selectRaw('store_id, COUNT(*) AS total')
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay()))
            ->groupBy('store_id')
            ->pluck('total', 'store_id');

        $rows = [];

        foreach ($names as $name) {
            $store = $stores[mb_strtolower($name)] ?? null;
            if (!$store) {
                $issues->push($this->issue(self::INFO, $name, 'Parduotuvės nėra stores lentelėje, nors ji yra config/flyer_scrapers.php'));

                continue;
            }

            $storeFlyers = $flyers[$store->id] ?? collect();
            $storeLink = ['store', $store->id];

            // Leaflet-only stores have discounts only through Gemini
            // extraction; e-shop stores get their own 0-active issue.
            if ($store->extract_discounts_from_flyer && !isset(config('scrapers')[$name]) && (int) ($activeByStore[$store->id] ?? 0) === 0) {
                $issues->push($this->issue(self::CRITICAL, $name, 'Nėra nė vienos aktyvios nuolaidos, nors Gemini ištraukimas iš leidinių įjungtas', null, $storeLink));
            }

            if ($storeFlyers->isEmpty()) {
                $issues->push($this->issue(self::INFO, $name, 'Leidinių neatėjo per ' . self::FLYER_LOOKBACK_DAYS . ' d.', null, $storeLink));
                $rows[] = ['store' => $name, 'store_id' => $store->id, 'flyer' => null, 'received_at' => null, 'days_left' => null, 'gemini' => $store->extract_discounts_from_flyer, 'linked' => null, 'store_active' => (int) ($activeByStore[$store->id] ?? 0)];

                continue;
            }

            $latestReceived = $storeFlyers->sortByDesc('created_at')->first();
            $newestDated = $storeFlyers->whereNotNull('valid_to')->sortByDesc('valid_to')->first();
            $undated = $storeFlyers->whereNull('valid_to');
            $current = $newestDated ?? $latestReceived;
            $daysLeft = $newestDated ? (int) $today->diffInDays($newestDated->valid_to, false) : null;
            $newerUndated = $newestDated && $undated->contains(fn (StoreFlyer $f) => $f->created_at->gt($newestDated->created_at));

            if ($newestDated && $daysLeft <= 2 && !$newerUndated) {
                $flyerLink = ['flyer', $newestDated->id];
                if ($daysLeft < 0) {
                    $issues->push($this->issue(
                        self::CRITICAL,
                        $name,
                        'Leidinys baigė galioti ' . $newestDated->valid_to->format('m-d') . ', naujas neatėjo (paskutinis gautas ' . $latestReceived->created_at->format('m-d') . ')',
                        -$daysLeft . ' d.',
                        $flyerLink,
                    ));
                } elseif ($daysLeft === 0) {
                    $issues->push($this->issue(self::CRITICAL, $name, 'Leidinys galioja tik iki šiandien, naujesnio nėra', 'šiandien', $flyerLink));
                } else {
                    $issues->push($this->issue(self::WARNING, $name, 'Leidinys baigiasi per 1–2 d., naujesnio nėra', null, $flyerLink));
                }
            }

            if ($undated->isNotEmpty()) {
                $message = 'Leidiniai be valid_to laikomi galiojančiais amžinai: ' . $undated->sortBy('id')->map(fn (StoreFlyer $f) => "#{$f->id}")->implode(', ')
                    . ' (seniausias gautas ' . $undated->min('created_at')->format('m-d') . ')';
                if ($store->extract_discounts_from_flyer) {
                    $message .= '. Gemini ištraukimas jiems praleidžiamas';
                }
                $issues->push($this->issue(self::WARNING, $name, $message, null, ['flyer', $undated->sortByDesc('id')->first()->id]));
            }

            foreach ($storeFlyers as $flyer) {
                $isLive = !$flyer->valid_to || $flyer->valid_to->gte($today);
                if (!$isLive) {
                    continue;
                }

                $issues = $issues->merge($this->flyerProcessingIssues($name, $flyer, $store, (int) ($linked[$flyer->id] ?? 0), (int) ($activeByStore[$store->id] ?? 0)));
            }

            $rows[] = [
                'store' => $name,
                'store_id' => $store->id,
                'flyer' => $current,
                'received_at' => $latestReceived->created_at,
                'days_left' => $daysLeft,
                'gemini' => $store->extract_discounts_from_flyer,
                'linked' => (int) ($linked[$current->id] ?? 0),
                'store_active' => (int) ($activeByStore[$store->id] ?? 0),
            ];
        }

        // Unfinished Gemini retry state on leaflets that no longer matter
        // (expired, or the store's extraction is off): left over from
        // earlier runs, shown only as a count.
        $leftover = StoreFlyer::query()
            ->whereNotNull('discounts_retry_state')
            ->whereNull('discounts_processed_at')
            ->where(fn ($q) => $q
                ->where('valid_to', '<', $today)
                ->orWhereHas('store', fn ($s) => $s->where('extract_discounts_from_flyer', false)))
            ->get(['id', 'updated_at']);

        if ($leftover->isNotEmpty()) {
            $issues->push($this->issue(
                self::INFO,
                $leftover->count() . ' leidiniai',
                'Seni nebaigto Gemini ištraukimo įrašai (pasibaigę leidiniai arba ištraukimas išjungtas, nuo ' . $leftover->min('updated_at')->format('m-d') . '): ignoruojama',
            ));
        }

        usort($rows, fn (array $a, array $b) => [$a['days_left'] === null ? -1 : 0, $a['days_left'] ?? 0] <=> [$b['days_left'] === null ? -1 : 0, $b['days_left'] ?? 0]);

        return ['rows' => $rows, 'issues' => $issues];
    }

    private function flyerProcessingIssues(string $name, StoreFlyer $flyer, Store $store, int $linked, int $storeActive): Collection
    {
        $issues = collect();
        $link = ['flyer', $flyer->id];
        $stuckBefore = now()->subMinutes(self::FLYER_STUCK_MINUTES);

        if ($flyer->processing_status === StoreFlyer::STATUS_FAILED) {
            $issues->push($this->issue(self::WARNING, $name, "Leidinio #{$flyer->id} puslapių apdorojimas nepavyko" . ($flyer->processing_error ? ': ' . str($flyer->processing_error)->limit(120) : ''), $flyer->updated_at->format('m-d'), $link));
        } elseif (in_array($flyer->processing_status, [StoreFlyer::STATUS_PENDING, StoreFlyer::STATUS_PROCESSING], true) && $flyer->updated_at->lt($stuckBefore)) {
            $issues->push($this->issue(self::WARNING, $name, "Leidinio #{$flyer->id} puslapiai įstrigę būsenoje „{$flyer->processing_status}“ nuo " . $flyer->updated_at->format('m-d H:i'), $flyer->updated_at->format('m-d'), $link));
        }

        // Gemini extraction only runs for valid_to strictly after today.
        if (!$store->extract_discounts_from_flyer || !$flyer->valid_to || $flyer->valid_to->lte(Carbon::today())) {
            return $issues;
        }

        $state = $flyer->discounts_retry_state ?? [];
        $attempts = (int) ($state['attempts'] ?? 0);

        if (!empty($state['is_quota_exceeded']) && !$flyer->discounts_processed_at) {
            $issues->push($this->issue(self::CRITICAL, $name, "Leidinys #{$flyer->id}: Gemini kvota/limitas viršytas, ištraukimas kartojamas ({$attempts} bandymas)", null, $link));
        } elseif (!empty($state['gave_up'])) {
            $issues->push($this->issue(self::WARNING, $name, "Leidinys #{$flyer->id}: Gemini ištraukimas nutrauktas po {$attempts} bandymų, nepavykę puslapiai: " . implode(', ', $state['failed_pages'] ?? []), null, $link));
        } elseif ($attempts >= self::GEMINI_ATTEMPTS_WARN) {
            $issues->push($this->issue(self::WARNING, $name, "Leidinys #{$flyer->id}: Gemini ištraukimas kartojamas ({$attempts} bandymas)" . (!empty($state['last_error_message']) ? ': ' . str($state['last_error_message'])->limit(100) : ''), null, $link));
        } elseif ($flyer->discounts_processed_at && $linked === 0 && $storeActive === 0) {
            $issues->push($this->issue(self::WARNING, $name, "Leidinys #{$flyer->id} Gemini apdorotas, bet 0 nuolaidų", $flyer->discounts_processed_at->format('m-d'), $link));
        } elseif (!$flyer->discounts_processed_at && !$state && $flyer->processing_status === StoreFlyer::STATUS_READY && $flyer->created_at->lt($stuckBefore)) {
            // flyers:process-discounts --pending only picks up leaflets from
            // the last 3 days, so one missed here is never retried.
            $issues->push($this->issue(self::WARNING, $name, "Leidinys #{$flyer->id} laukia Gemini ištraukimo nuo " . $flyer->created_at->format('m-d H:i'), $flyer->created_at->format('m-d'), $link));
        }

        return $issues;
    }

    private function globals(): array
    {
        $issues = collect();

        $unprocessed = DB::table('discount_temp')->where('processed', false)->selectRaw('COUNT(*) AS total, MIN(created_at) AS oldest')->first();

        $unmapped = DB::table('category_mappers')->whereNull('category_id')->count();
        if ($unmapped > 0) {
            $issues->push($this->issue(self::INFO, '—', "{$unmapped} nesusietų category_mappers (paleisk categories:map-mappers)"));
        }

        $failed = DB::table('failed_jobs')->selectRaw('queue, COUNT(*) AS total, MAX(failed_at) AS last_at')->groupBy('queue')->get();
        $failedTotal = (int) $failed->sum('total');
        $failedLast = $failed->max('last_at') ? Carbon::parse($failed->max('last_at')) : null;

        if ($failedTotal > 0) {
            $issues->push($this->issue(
                $failedLast->gte(now()->subDays(self::FAILED_JOBS_RECENT_DAYS)) ? self::WARNING : self::INFO,
                '—',
                "{$failedTotal} failed_jobs (" . $failed->map(fn ($row) => "{$row->queue}: {$row->total}")->implode(', ') . '), paskutinis ' . $failedLast->format('m-d H:i'),
                $failedLast->format('m-d'),
            ));
        }

        return [
            'issues' => $issues,
            'unprocessed' => ['total' => (int) $unprocessed->total, 'oldest' => $unprocessed->oldest ? Carbon::parse($unprocessed->oldest) : null],
            'unmapped_categories' => $unmapped,
            'failed_jobs' => ['total' => $failedTotal, 'last_at' => $failedLast, 'queues' => $failed->pluck('queue')->all()],
        ];
    }

    // Same severity + message for several stores ("leaflet ends today")
    // becomes one row listing the stores.
    private function groupIssues(Collection $issues): Collection
    {
        return $issues
            ->groupBy(fn (array $issue) => $issue['severity'] . '|' . $issue['message'])
            ->map(fn (Collection $group) => $group->count() === 1 ? $group->first() : [
                ...$group->first(),
                'store' => $group->pluck('store')->implode(', '),
                'link' => null,
            ])
            ->values();
    }

    private function storesByName(array $names): Collection
    {
        return Store::query()
            ->whereIn('name', $names)
            ->get(['id', 'name', 'slug', 'extract_discounts_from_flyer'])
            ->keyBy(fn (Store $store) => mb_strtolower($store->name));
    }

    private function issue(string $severity, string $store, string $message, ?string $since = null, ?array $link = null): array
    {
        return compact('severity', 'store', 'message', 'since', 'link');
    }

    private function median(array $values): ?int
    {
        $values = array_values(array_filter($values, fn (int $n) => $n > 0));
        if (!$values) {
            return null;
        }

        sort($values);
        $mid = intdiv(count($values), 2);

        return count($values) % 2 ? $values[$mid] : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    public static function formatDelta(?float $delta): string
    {
        if ($delta === null) {
            return '—';
        }

        $percent = (int) round($delta * 100);

        return ($percent > 0 ? '+' : ($percent < 0 ? '−' : '')) . abs($percent) . ' %';
    }

    private static function severityRank(string $severity): int
    {
        return match ($severity) {
            self::CRITICAL => 0,
            self::WARNING => 1,
            default => 2,
        };
    }
}
