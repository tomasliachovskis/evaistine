@props(['points', 'productName'])

@php
    // Server-computed once, over every point — this is what search engines
    // and no-JS visitors see; the Alpine component below re-derives the same
    // numbers client-side (from the same $points, passed as JSON) purely to
    // update them when a store filter is picked, not to first-paint them.
    $prices = $points->pluck('price');
    $minPrice = $prices->min();
    $maxPrice = $prices->max();
    $avgPrice = $prices->avg();
    $lastPoint = $points->sortByDesc('date')->first();
    $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';
    $fmtDate = fn ($date) => \Illuminate\Support\Carbon::parse($date)->translatedFormat('Y-m-d');

    $storeCounts = $points->groupBy('store_slug')->map(fn ($group) => [
        'slug' => $group->first()['store_slug'],
        'name' => $group->first()['store_name'],
        'count' => $group->count(),
    ])->sortByDesc('count')->values();

    $tableRows = $points->sortByDesc('date')->values();
@endphp

<div x-data="priceHistoryChart(@js($points->values()))">
    <h2 class="mb-1 text-lg font-bold text-gray-900">{{ \App\Support\ProductPageMeta::historyTitle($productName) }}</h2>
    <p class="mb-4 text-sm text-gray-600" x-text="summaryLine">
        Paskutinė žinoma kaina — {{ $euro($lastPoint['price']) }} ({{ $fmtDate($lastPoint['date']) }}){{ $minPrice == $lastPoint['price'] ? ', tai istorinis minimumas.' : '. Žemiausia buvo ' . $euro($minPrice) . '.' }}
    </p>

    @if ($storeCounts->count() > 1)
        <nav aria-label="Filtruoti pagal parduotuvę" class="scroll-cards-x mb-4 flex flex-nowrap items-center gap-2">
            <button
                type="button"
                @click="setStore(null)"
                :class="activeStore === null ? 'border-green bg-action text-white' : 'border-gray-200 text-gray-700 hover:border-green/40'"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-bold transition-colors min-h-12"
            >
                Visos parduotuvės
                <span class="text-xs font-semibold opacity-75">{{ $storeCounts->count() }}</span>
            </button>
            @foreach ($storeCounts as $store)
                <button
                    type="button"
                    @click="setStore('{{ $store['slug'] }}')"
                    :class="activeStore === '{{ $store['slug'] }}' ? 'border-green bg-action text-white' : 'border-gray-200 text-gray-700 hover:border-green/40'"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-bold transition-colors min-h-12"
                >
                    {{ $store['name'] }}
                </button>
            @endforeach
        </nav>
    @endif

    <div class="relative">
        {{-- Gridlines/labels/line/area/points are all built imperatively in
             renderChart() (resources/js/app.js) instead of declarative
             x-for — Alpine's <template> content is always parsed in the
             HTML namespace even when nested inside <svg>, so cloned
             <text>/<circle> elements silently fail to render as real SVG. --}}
        <svg x-ref="svg" viewBox="0 0 760 220" preserveAspectRatio="none" class="block w-full overflow-visible" role="img" :aria-label="summaryLine">
            <defs>
                <linearGradient id="priceHistoryFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#09a952" stop-opacity="0.16" />
                    <stop offset="100%" stop-color="#09a952" stop-opacity="0" />
                </linearGradient>
            </defs>
        </svg>
        <div
            x-show="tooltip.visible"
            x-cloak
            x-transition.opacity.duration.100ms
            class="pointer-events-none absolute z-10 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs font-semibold text-white"
            :style="`left:${tooltip.x}px;top:${tooltip.y}px;transform:translate(${tooltip.shift},-115%)`"
        >
            <span x-text="tooltip.price"></span>
            <span class="block text-xs font-medium opacity-75" x-text="tooltip.sub"></span>
        </div>
    </div>

    <div class="mb-5 mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-action"></span>Kaina (paspauskite tašką)</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-action"></span>Žemiausia iki šiol</span>
    </div>

    <div class="grid max-w-md grid-cols-3 gap-3">
        <div class="rounded-xl border border-gray-100 p-3 text-center">
            <p class="text-xs font-medium text-gray-500">Mažiausia</p>
            <p class="mt-1 text-base font-bold text-dark-green" x-text="fmtPrice(minPrice)">{{ $euro($minPrice) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 p-3 text-center">
            <p class="text-xs font-medium text-gray-500">Vidutinė</p>
            <p class="mt-1 text-base font-bold text-gray-900" x-text="fmtPrice(avgPrice)">{{ $euro($avgPrice) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 p-3 text-center">
            <p class="text-xs font-medium text-gray-500">Didžiausia</p>
            <p class="mt-1 text-base font-bold text-gray-900" x-text="fmtPrice(maxPrice)">{{ $euro($maxPrice) }}</p>
        </div>
    </div>

    {{-- Always server-rendered, real text — the SVG above is a progressive
         enhancement for sighted JS users; this table is what a search
         crawler (or a no-JS visitor) actually reads. Not re-rendered by
         Alpine on filter change: it always lists every store's full history,
         same as before filtering (only the chart/cards above narrow down). --}}
    <details class="mt-5 max-w-2xl" open>
        <summary class="cursor-pointer text-sm font-semibold text-dark-green hover:underline">Visi kainų įrašai lentelėje</summary>
        <div class="mt-3 max-h-80 overflow-y-auto rounded-xl border border-gray-200">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only">{{ $productName }} — kainų istorija pagal parduotuvę, nuo naujausios</caption>
                <thead>
                    <tr class="sticky top-0 bg-white">
                        <th scope="col" class="border-b border-gray-200 px-3.5 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">Data</th>
                        <th scope="col" class="border-b border-gray-200 px-3.5 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">Parduotuvė</th>
                        <th scope="col" class="border-b border-gray-200 px-3.5 py-2 text-left text-xs font-bold uppercase tracking-wide text-gray-400">Kaina</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tableRows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="border-b border-gray-100 px-3.5 py-2 text-gray-700">{{ $fmtDate($row['date']) }}</td>
                            <td class="border-b border-gray-100 px-3.5 py-2 text-gray-700">{{ $row['store_name'] }}</td>
                            <td class="border-b border-gray-100 px-3.5 py-2 font-bold tabular-nums {{ $row['price'] == $minPrice ? 'text-dark-green' : 'text-gray-900' }}">{{ $euro($row['price']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
