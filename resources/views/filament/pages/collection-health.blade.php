@php
    use App\Services\CollectionHealthService as Health;

    $today = now()->startOfDay();
    $severityBadge = [
        Health::CRITICAL => ['KRIT', 'danger'],
        Health::WARNING => ['ĮSP', 'warning'],
        Health::INFO => ['INFO', 'info'],
    ];
    $relative = function ($at) use ($today) {
        if (!$at) {
            return '—';
        }
        if ($at->gte($today)) {
            return 'šiandien ' . $at->format('H:i');
        }
        if ($at->gte($today->copy()->subDay())) {
            return 'vakar ' . $at->format('H:i');
        }

        return $at->format('m-d H:i');
    };
@endphp

<x-filament-panels::page>
    <style>
        .ch { --ch-line: rgb(229 231 235); --ch-muted: rgb(107 114 128); --ch-head: rgb(249 250 251); --ch-crit: rgb(220 38 38); --ch-crit-bg: rgb(254 242 242); --ch-warn: rgb(180 83 9); --ch-warn-bg: rgb(255 251 235); --ch-bar: rgb(156 163 175); --ch-bar-last: rgb(17 24 39); }
        .dark .ch { --ch-line: rgb(255 255 255 / .1); --ch-muted: rgb(156 163 175); --ch-head: rgb(255 255 255 / .03); --ch-crit-bg: rgb(220 38 38 / .12); --ch-warn-bg: rgb(245 158 11 / .1); --ch-bar: rgb(107 114 128); --ch-bar-last: rgb(243 244 246); }
        .ch-tiles { display: flex; flex-wrap: wrap; gap: .75rem; }
        .ch-tile { flex: 1 1 170px; border: 1px solid var(--ch-line); border-radius: .75rem; padding: .75rem 1rem; }
        .ch-tile.crit { border-left: 4px solid var(--ch-crit); }
        .ch-tile.warn { border-left: 4px solid #f59e0b; }
        .ch-tile .v { font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.2; }
        .ch-tile.crit .v { color: var(--ch-crit); }
        .ch-tile.warn .v { color: var(--ch-warn); }
        .ch-tile .l { color: var(--ch-muted); font-size: .75rem; }
        .ch-scroll { overflow-x: auto; }
        .ch table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .ch th, .ch td { padding: .55rem .75rem; border-bottom: 1px solid var(--ch-line); text-align: left; white-space: nowrap; vertical-align: middle; }
        .ch th { font-size: .75rem; color: var(--ch-muted); font-weight: 600; background: var(--ch-head); }
        .ch tr:last-child td { border-bottom: 0; }
        .ch .num { text-align: right; font-variant-numeric: tabular-nums; }
        .ch .wrap { white-space: normal; min-width: 18rem; }
        .ch .fit { width: 1%; }
        .ch-badge { display: inline-block; font-size: .75rem; font-weight: 600; line-height: 1.25rem; padding: 0 .5rem; border-radius: 999px; white-space: nowrap; }
        .ch-badge.danger { background: var(--ch-crit); color: #fff; }
        .ch-badge.warning { background: #f59e0b; color: #111827; }
        .ch-badge.info { background: rgb(37 99 235 / .12); color: rgb(37 99 235); }
        .ch-badge.success { background: rgb(22 163 74 / .14); color: rgb(21 128 61); }
        .ch-badge.gray { background: rgb(107 114 128 / .14); color: var(--ch-muted); }
        .dark .ch-badge.info { color: rgb(96 165 250); }
        .dark .ch-badge.success { color: rgb(74 222 128); }
        .ch .store-col { white-space: normal; min-width: 8rem; max-width: 16rem; }
        .ch .muted { color: var(--ch-muted); }
        .ch tr.crit { background: var(--ch-crit-bg); }
        .ch tr.warn { background: var(--ch-warn-bg); }
        .ch a.ch-link { color: var(--primary-600, rgb(22 163 74)); text-decoration: none; }
        .ch a.ch-link:hover { text-decoration: underline; }
        .ch .legend { font-size: .75rem; color: var(--ch-muted); }
        .ch .ch-empty { padding: 1rem; color: var(--ch-muted); }
    </style>

    <div class="ch" wire:poll.60s style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="ch-tiles">
            <div class="ch-tile {{ $report['counts'][Health::CRITICAL] ? 'crit' : '' }}">
                <div class="v">{{ $report['counts'][Health::CRITICAL] }}</div>
                <div class="l">Kritinės</div>
            </div>
            <div class="ch-tile {{ $report['counts'][Health::WARNING] ? 'warn' : '' }}">
                <div class="v">{{ $report['counts'][Health::WARNING] }}</div>
                <div class="l">Įspėjimai</div>
            </div>
            <div class="ch-tile">
                <div class="v">{{ $report['latest_eshop_row_at']?->format('H:i') ?? '—' }}</div>
                <div class="l">Naujausia e-shop eilutė ({{ $report['latest_eshop_row_at'] ? $relative($report['latest_eshop_row_at']) : 'nėra' }})</div>
            </div>
            <div class="ch-tile">
                <div class="v">{{ $report['unprocessed']['total'] }}</div>
                <div class="l">Neapdorota discount_temp{{ $report['unprocessed']['oldest'] ? ' (seniausia ' . $report['unprocessed']['oldest']->format('m-d H:i') . ')' : '' }}</div>
            </div>
            <div class="ch-tile">
                <div class="v">{{ $report['unmapped_categories'] }}</div>
                <div class="l">Nesusietos kategorijos</div>
            </div>
            <div class="ch-tile">
                <div class="v">{{ $report['failed_jobs']['total'] }}</div>
                <div class="l">failed_jobs{{ $report['failed_jobs']['last_at'] ? ' (pask. ' . $report['failed_jobs']['last_at']->format('m-d') . ')' : '' }}</div>
            </div>
        </div>

        <x-filament::section :heading="'Reikia dėmesio'" :description="'Rūšiuota pagal svarbą. Atnaujinta ' . $report['generated_at']->format('H:i') . ', puslapis atsinaujina kas 60 s.'" compact>
            @if (empty($report['issues']))
                <div class="ch-empty">Viskas tvarkoje.</div>
            @else
                <div class="ch-scroll">
                    <table>
                        <thead><tr><th>Lygis</th><th>Parduotuvė</th><th>Kas negerai</th><th>Nuo</th></tr></thead>
                        <tbody>
                            @foreach ($report['issues'] as $issue)
                                @php [$label, $color] = $severityBadge[$issue['severity']]; $url = $linkUrl($issue['link']); @endphp
                                <tr class="{{ ['critical' => 'crit', 'warning' => 'warn'][$issue['severity']] ?? '' }}">
                                    <td class="fit"><span class="ch-badge {{ $color }}">{{ $label }}</span></td>
                                    <td class="store-col">
                                        @if ($url)
                                            <a class="ch-link" href="{{ $url }}">{{ $issue['store'] }}</a>
                                        @else
                                            {{ $issue['store'] }}
                                        @endif
                                    </td>
                                    <td class="wrap">{{ $issue['message'] }}</td>
                                    <td class="muted">{{ $issue['since'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="E-shop scraperiai" :description="'Unikalios prekės per dieną (COUNT(DISTINCT product_url), be leidinių Gemini eilučių), ' . \Illuminate\Support\Carbon::parse($report['days'][0])->format('m-d') . ' → ' . \Illuminate\Support\Carbon::parse(end($report['days']))->format('m-d') . '. Bazė = dienų prieš 8–14 d. mediana. Raudonas stulpelis = diena be duomenų.'" compact>
            <div class="ch-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Parduotuvė</th>
                            <th>Paskutinė eilutė</th>
                            <th class="num">Paskutinė diena</th>
                            <th class="num">Bazė</th>
                            <th class="num">Pokytis</th>
                            <th>{{ count($report['days']) }} d.</th>
                            <th class="num">Neapdor.</th>
                            <th class="num">Aktyvių nuolaidų</th>
                            <th class="num" title="Unikalios prekės per paskutines 24 val.; po jomis eilutės, kurių discounts:process nepanaudoja">Atėjo 24 val.</th>
                            <th class="num" title="Naujai sukurtos e-shop nuolaidos per 24 val.">Naujų 24 val.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['eshop'] as $row)
                            @php
                                $stale = !$row['last_at'] || $row['last_at']->lt(now()->subHours(30));
                                $rowClass = $stale ? 'crit' : ($row['is_anomaly'] ? 'warn' : '');
                                $url = $linkUrl($row['store_id'] ? ['store', $row['store_id']] : null);
                                $max = max(1, ...$row['series']);
                                $barWidth = 150 / count($row['series']);
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>@if ($url)<a class="ch-link" href="{{ $url }}">{{ $row['store'] }}</a>@else{{ $row['store'] }}@endif</td>
                                <td>{{ $relative($row['last_at']) }}</td>
                                <td class="num">{{ $row['latest'] ?? '—' }}@if ($row['latest_day'] && $row['latest_day'] !== end($report['days']))<span class="muted"> ({{ \Illuminate\Support\Carbon::parse($row['latest_day'])->format('m-d') }})</span>@endif</td>
                                <td class="num">{{ $row['baseline'] ?? '—' }}</td>
                                <td class="num">
                                    @if ($row['is_anomaly'])
                                        <span class="ch-badge warning">{{ Health::formatDelta($row['delta']) }}</span>
                                    @else
                                        <span class="muted">{{ Health::formatDelta($row['delta']) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <svg width="150" height="30" viewBox="0 0 150 30" style="display: block;">
                                        @foreach ($row['series'] as $i => $value)
                                            @php $h = $value === 0 ? 3 : max(2, $value / $max * 28); @endphp
                                            <rect x="{{ round($i * $barWidth + 1, 1) }}" y="{{ round(30 - $h, 1) }}" width="{{ round($barWidth - 2, 1) }}" height="{{ round($h, 1) }}" rx="1"
                                                  style="fill: {{ $value === 0 ? 'var(--ch-crit)' : ($loop->last ? 'var(--ch-bar-last)' : 'var(--ch-bar)') }};"><title>{{ \Illuminate\Support\Carbon::parse($report['days'][$i])->format('m-d') }}: {{ $value }}</title></rect>
                                        @endforeach
                                    </svg>
                                </td>
                                <td class="num">
                                    @if ($row['unprocessed'] > 0)
                                        <span title="seniausia {{ $row['unprocessed_oldest']?->format('m-d H:i') }}">{{ $row['unprocessed'] }}</span>
                                    @else
                                        <span class="muted">0</span>
                                    @endif
                                </td>
                                <td class="num">{{ $row['active'] ?? '—' }}</td>
                                @php
                                    $last = $row['last_24h'];
                                    $bad = array_filter(['be datų' => $last['no_dates'], 'pasibaigę' => $last['expired'], 'be kainos' => $last['no_price']]);
                                @endphp
                                <td class="num">
                                    {{ $last['products'] }}
                                    @if ($bad)
                                        <div class="legend" style="color: var(--ch-crit);">{{ collect($bad)->map(fn ($n, $label) => "{$label} {$n}")->implode(', ') }}</div>
                                    @endif
                                </td>
                                <td class="num">{{ $last['created'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Leidiniai" description="Naujausias (vėliausiai galiojantis) leidinys per parduotuvę iš config/flyer_scrapers.php. Nuolaidos iš leidinio = discounts su šio leidinio store_flyer_id." compact>
            <div class="ch-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Parduotuvė</th>
                            <th>Paskutinis gautas</th>
                            <th>Galioja</th>
                            <th class="num">Liko d.</th>
                            <th>Puslapiai</th>
                            <th>Nuolaidos iš leidinio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['flyers'] as $row)
                            @php
                                $flyer = $row['flyer'];
                                $left = $row['days_left'];
                                $rowClass = $flyer && $left !== null && $left <= 0 ? 'crit' : ($flyer && ($left === null || $left <= 2) ? 'warn' : '');
                                $flyerUrl = $flyer ? $linkUrl(['flyer', $flyer->id]) : null;
                                $storeUrl = $linkUrl(['store', $row['store_id']]);
                                $statusColor = match ($flyer?->processing_status) {
                                    'ready' => 'success',
                                    'failed' => 'danger',
                                    'pending', 'processing' => 'warning',
                                    default => 'gray',
                                };
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td><a class="ch-link" href="{{ $storeUrl }}">{{ $row['store'] }}</a></td>
                                <td>{{ $row['received_at']?->format('m-d') ?? '—' }}</td>
                                <td>
                                    @if (!$flyer)
                                        <span class="muted">nėra per 90 d.</span>
                                    @else
                                        <a class="ch-link" href="{{ $flyerUrl }}">#{{ $flyer->id }}</a>
                                        {{ $flyer->valid_from?->format('m-d') ?? '?' }} → {{ $flyer->valid_to?->format('m-d') ?? '?' }}
                                    @endif
                                </td>
                                <td class="num">
                                    @if (!$flyer)
                                        <span class="muted">—</span>
                                    @elseif ($left === null)
                                        <span class="ch-badge warning">be datų</span>
                                    @elseif ($left < 0)
                                        <span class="ch-badge danger">{{ $left }}</span>
                                    @elseif ($left === 0)
                                        <span class="ch-badge danger">šiandien</span>
                                    @elseif ($left <= 2)
                                        <span class="ch-badge warning">{{ $left }}</span>
                                    @else
                                        <span class="muted">{{ $left }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($flyer)
                                        <span class="ch-badge {{ $statusColor }}">{{ $flyer->processing_status }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!$row['gemini'])
                                        <span class="ch-badge gray">išjungta</span>
                                    @elseif (!$flyer)
                                        <span class="muted">—</span>
                                    @elseif (!empty($flyer->discounts_retry_state['attempts']) && !$flyer->discounts_processed_at)
                                        <span class="ch-badge warning">kartojama, {{ $flyer->discounts_retry_state['attempts'] }} bandymas</span>
                                    @elseif (!$flyer->discounts_processed_at)
                                        <span class="muted">{{ !empty($flyer->discounts_retry_state['skipped_reason']) ? 'praleista: ' . $flyer->discounts_retry_state['skipped_reason'] : 'laukia' }}</span>
                                    @elseif ($row['linked'] > 0)
                                        <span class="ch-badge success">{{ $row['linked'] }}</span>
                                    @elseif ($row['store_active'] > 0)
                                        <span class="muted">0 susietų (parduotuvėje {{ $row['store_active'] }} aktyvių)</span>
                                    @else
                                        <span class="ch-badge warning">0 nuolaidų</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
