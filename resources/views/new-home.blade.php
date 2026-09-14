<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">

    {{-- Hero: comparison-first pitch + a real, working search box (same
         redirect pattern as akcijos/search-form.blade.php) — search is the
         primary action here, not an afterthought. --}}
    <div class="border-b border-gray-100 bg-green/5">
        <div class="base-container mx-auto flex flex-col items-center gap-4 py-10 text-center sm:py-14">
            <span class="inline-flex items-center gap-1.5 text-sm font-extrabold uppercase tracking-wide text-green">
                <span class="size-1.5 rounded-full bg-green"></span>
                Akcijos ir nuolaidos Lietuvoje
            </span>
            <h1 class="max-w-2xl font-extrabold text-gray-900">Kur pigiausia pirkti — palyginome už tave</h1>
            <p class="max-w-xl text-base leading-snug text-gray-600 sm:text-lg">
                {{-- 20 is a static number by explicit product decision, not
                     derived from active_store_count anymore. --}}
                Palygink kasdienių prekių kainas Maxima, Lidl, Iki, Rimi, Norfa ir dar 20 kitų parduotuvių.
            </p>

            <form
                method="get"
                action=""
                class="flex w-full max-w-xl items-center gap-2 rounded-full border-2 border-gray-200 bg-white p-1.5 pl-5 shadow-sm"
                x-data="{ q: '' }"
                @submit.prevent="if (q.trim()) window.location = '/akcijos/paieska/' + encodeURIComponent(q.trim())"
            >
                <input
                    type="search"
                    name="q"
                    x-model="q"
                    placeholder="Pienas, kava, kiaulienos nugarinė…"
                    class="min-w-0 flex-1 border-none bg-transparent text-base outline-none sm:text-lg"
                >
                <button type="submit" class="shrink-0 rounded-full bg-green px-5 py-3 text-base font-bold text-white transition-colors hover:bg-dark-green">
                    Ieškoti
                </button>
            </form>

            <p class="text-sm text-gray-500 sm:text-base">
                <strong class="text-gray-700 tabular-nums">{{ $stats['total_deals_label'] }}+</strong> aktyvių akcijų šiuo metu ·
                <strong class="text-gray-700 tabular-nums">{{ $stats['new_today_count_label'] }}</strong> naujų šiandien
            </p>
        </div>
    </div>

    <div class="bg-background">
        <div class="base-container mx-auto flex flex-col gap-8 py-6 sm:gap-16 sm:py-10">

            {{-- Comparison teaser — the homepage's actual spine. Each block
                 (food / non-food) shows 2 keyword pages' own cheapest-per-
                 store comparison (KeywordPageService::buildHomeTeaser()) —
                 one small "Žiūrėti visas {label}" link per keyword item,
                 straight to that keyword page, not one link per whole block
                 to a generic page anymore. --}}
            @foreach ($comparisonCategories as $category)
                <div>
                    <h2 class="section-heading">{{ $category['name'] }}</h2>

                    <div class="mt-4 flex flex-col gap-5">
                        @foreach ($category['items'] as $item)
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-lg font-semibold text-gray-600">
                                        {{ $item['label'] }}
                                    </p>
                                    <a href="{{ $item['href'] }}" class="section-link shrink-0 text-sm">
                                        Žiūrėti visas
                                        <x-app-icon name="chevron-right" class="size-3.5 opacity-80" />
                                    </a>
                                </div>
                                {{-- leading_deals is already the real <x-deal-card>
                                     shape (DiscountResponseFormatter::formatListDiscount()),
                                     one cheapest offer per store, capped at 5 — same
                                     data the keyword page's own grid leads with, no
                                     reshaping needed. 4 visible on mobile, 5 at lg+
                                     (same "hide the extra one on mobile" trick used
                                     in landing-deals-section). --}}
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                                    @foreach ($item['leading_deals'] as $index => $deal)
                                        <div class="{{ $index >= 4 ? 'hidden lg:block' : '' }}">
                                            <x-deal-card :deal="$deal" source="home_comparison" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Store row: the 5 main chains, real discount counts. Same
                 section-card + heading pattern as the comparison blocks
                 above it, instead of floating bare on the page background. --}}
            <div class="section-card">
                <h2 class="section-heading">Didžiausi Lietuvos parduotuvių tinklai</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    @foreach ($stores as $store)
                        <x-store-card :store="$store" layout="grid" />
                    @endforeach
                </div>
            </div>


            {{-- Leaflets — same frame + card language as the rest of the page
                 (rounded-xl border, image on top, small store logo, minimal
                 text, whole card as one link) instead of <x-leaflet-card>'s
                 own heavier look (uppercase label, date range, full-width
                 button) — that design is right for /leidiniai itself, but
                 next to the comparison/deal cards here it read as a
                 different product. Same grid as the deals section above, no
                 carousel, for the same reason. --}}
            @if (count($latestLeaflets))
                <div class="section-card">
                    <div class="section-heading-row">
                        <h2 class="section-heading">Naujausi akcijų leidiniai</h2>
                        <a href="/leidiniai" class="section-link text-base">
                            Žiūrėti visus
                            <x-app-icon name="chevron-right" class="size-4 opacity-80" />
                        </a>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($latestLeaflets as $leaflet)
                            @php
                                $isExpired = $leaflet['status'] === 'expired';
                                $days = $leaflet['days_remaining'] ?? null;
                                $href = $leaflet['view_url'] ?? "/leidinys/{$leaflet['store_slug']}";
                            @endphp
                            <a href="{{ $href }}" class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 hover:opacity-80">
                                <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-white">
                                    @if (!empty($leaflet['thumbnail_url'] ?? $leaflet['image_url'] ?? null))
                                        <img src="{{ $leaflet['thumbnail_url'] ?? $leaflet['image_url'] }}" alt="{{ $leaflet['title'] ?? $leaflet['store_name'] }}" loading="lazy" class="h-full w-full object-contain p-2 {{ $isExpired ? 'grayscale' : '' }}">
                                    @else
                                        <div class="flex h-full items-center justify-center">
                                            <x-store-logo :slug="$leaflet['store_slug']" :name="$leaflet['store_name']" size="md" />
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <x-store-logo :slug="$leaflet['store_slug']" :name="$leaflet['store_name']" size="xs" />
                                    <span class="text-[0.78em] font-semibold text-gray-500">{{ $leaflet['store_name'] }}</span>
                                </div>
                                <p class="line-clamp-2 text-[0.9em] font-semibold leading-tight text-gray-900">{{ $leaflet['title'] ?? $leaflet['store_name'] }}</p>
                                <span @class([
                                    'text-[0.78em] font-semibold',
                                    'text-gray-400' => $isExpired,
                                    'text-red-600' => !$isExpired && $days !== null && $days <= 2,
                                    'text-green' => !$isExpired && ($days === null || $days > 2),
                                ])>
                                    {{ $isExpired ? 'Nebegalioja' : ($days !== null ? "Galioja dar {$days} d." : 'Galioja') }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-x-6 gap-y-2 border-t border-gray-200 pt-5 text-base text-gray-600">
                <span><strong class="text-gray-900 tabular-nums">{{ $stats['total_deals_label'] }}</strong> aktyvios akcijos</span>
                <span><strong class="text-gray-900 tabular-nums">{{ $stats['active_store_count'] }}</strong> parduotuvės su akcijomis</span>
                @if ($stats['top_discount_percent'])
                    <span><strong class="text-gray-900 tabular-nums">{{ $stats['top_discount_percent'] }}%</strong> didžiausia nuolaida šiandien</span>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
