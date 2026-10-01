<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">

    {{-- Hero: comparison-first pitch + a real, working search box (same
         redirect pattern as akcijos/search-form.blade.php) — search is the
         primary action here, not an afterthought. --}}
    <div class="relative overflow-hidden border-b border-gray-100 bg-green/5">
        <div class="base-container relative z-10 mx-auto flex flex-col items-center gap-4 py-6 text-center sm:py-8">
            {{-- Dot inline with the text, so it stays with the first word
                 when the line wraps on narrow phones. --}}
            <span class="text-sm font-extrabold uppercase tracking-wide text-dark-green">
                <span class="mr-1.5 inline-block size-1.5 rounded-full bg-action align-middle"></span>Akcijos ir nuolaidos Lietuvoje
            </span>
            <h1 class="max-w-2xl font-extrabold text-gray-900">Kur pigiausia pirkti — palyginome už tave</h1>
            <p class="max-w-xl text-base leading-snug text-gray-600 sm:text-lg">
                {{-- 20 is a static number by explicit product decision, not
                     derived from active_store_count anymore. --}}
                Palygink kasdienių prekių kainas Maxima, Lidl, Iki, Rimi, Norfa ir dar 20+ parduotuvių.
            </p>

            <form
                method="get"
                action=""
                class="flex w-full max-w-xl items-center gap-2 rounded-full border border-gray-200 bg-white p-1.5 pl-5 shadow-sm"
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
                <button type="submit" class="shrink-0 rounded-full bg-action px-5 py-3 text-base font-bold text-white transition-colors hover:bg-action-hover">
                    Ieškoti
                </button>
            </form>

            {{-- Lets someone start comparing without typing anything —
                 straight to real keyword pages, same ones the search box
                 itself would land on for these terms. --}}
            <div class="hidden flex-wrap items-center justify-center gap-2 sm:flex">
                @foreach (['kava' => 'Kava', 'sviestas' => 'Sviestas', 'pienas' => 'Pienas', 'kiausiniai' => 'Kiaušiniai'] as $slug => $label)
                    <a href="/akcijos/{{ $slug }}" class="rounded-full border border-gray-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-gray-700 transition-colors hover:border-green hover:text-dark-green min-h-12 inline-flex items-center">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <p class="text-sm text-gray-500 sm:text-base">
                {{-- Rounded down to the nearest thousand — the exact live
                     count changes minute to minute, so a precise-looking
                     "15 033+" read as an odd, oddly-specific number. --}}
                <strong class="text-gray-700 tabular-nums">{{ number_format((int) floor($stats['total_deals'] / 1000) * 1000, 0, '', ' ') }}+</strong> aktyvių akcijų šiuo metu
                <span class="hidden sm:inline">
                    · <strong class="text-gray-700 tabular-nums">{{ $stats['new_today_count_label'] }}</strong> naujų šiandien
                </span>
            </p>

            {{-- Trust row — real logos of the 5 main chains the subtitle
                 above already names, so the pitch isn't just a text claim.
                 Horizontally scrollable on mobile instead of wrapping, same
                 "don't grow the hero" reasoning as everything else here. --}}
            <div class="flex w-full max-w-xl flex-col items-center gap-2">
                <div class="scroll-cards-x flex w-full items-center justify-start gap-2 sm:justify-center sm:flex-wrap">
                    @foreach (['maxima', 'lidl', 'iki', 'rimi', 'norfa'] as $slug)
                        <a href="/akcijos/{{ $slug }}" data-ga-event="filter_select" data-ga-item="store:{{ $slug }}" data-ga-source="home_trust_row" class="flex min-h-12 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 transition-colors hover:border-green/40">
                            <x-store-logo :slug="$slug" size="sm" />
                        </a>
                    @endforeach
                    <a href="/parduotuves" data-ga-event="filter_select" data-ga-item="store:all" data-ga-source="home_trust_row" class="inline-flex min-h-12 shrink-0 items-center whitespace-nowrap px-3 text-base font-semibold text-dark-green underline underline-offset-4 hover:no-underline">+20 kitų</a>
                </div>
            </div>
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
                    <h2 class="section-heading-lg">{{ $category['name'] }}</h2>

                    <div class="mt-4 flex flex-col gap-8 sm:gap-10">
                        @foreach ($category['items'] as $item)
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-lg font-semibold text-gray-600">
                                        {{ $item['label'] }}
                                    </p>
                                    <a href="{{ $item['href'] }}" class="section-link">
                                        Žiūrėti visas{{ !empty($item['matching_offers_count']) ? ' ('.number_format($item['matching_offers_count'], 0, ',', ' ').')' : '' }}
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
                @if ($loop->first)
                    <x-signup-inline-card />
                @endif
            @endforeach

            {{-- Store row: the 5 main chains, real discount counts. Same
                 section-card + heading pattern as the comparison blocks
                 above it, instead of floating bare on the page background. --}}
            <div>
                <div class="section-heading-row">
                    <h2 class="section-heading-lg">Parduotuvių tinklai</h2>
                    <a href="/parduotuves" class="section-link">
                        Žiūrėti visas
                        <x-app-icon name="chevron-right" class="size-4 opacity-80" />
                    </a>
                </div>
                {{-- Two to a row on phones (a sideways strip hid most of
                     them from older readers), a 5-col grid at sm+. --}}
                <div class="mt-4 flex flex-wrap gap-3 sm:grid sm:grid-cols-5">
                    @foreach ($stores as $store)
                        <div class="w-[calc(50%-0.375rem)] sm:w-auto">
                            <x-store-card :store="$store" layout="grid" />
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Category slider: same horizontal snap-scroll pattern as the
                 stores row above. Icons rendered grayscale — this row is a
                 quick-nav strip, not a place to compete visually with the
                 real product photos in the sections around it, so icons
                 stay plain black/white instead of their default full color. --}}
            @if (count($categories))
                <div>
                    <h2 class="section-heading-lg">Kategorijos</h2>
                    {{-- Phones: a two-column list of the first 6 with a button
                         that opens the rest in place, instead of a sideways
                         strip. From sm up, the strip as before. --}}
                    <div x-data="{ all: false }">
                        <div class="mt-4 flex flex-wrap gap-2 sm:snap-x sm:snap-mandatory sm:flex-nowrap sm:gap-3 sm:overflow-x-auto sm:py-2 sm:[scrollbar-width:none] sm:[&::-webkit-scrollbar]:hidden">
                            @foreach ($categories as $category)
                                <a href="/akcijos/{{ $category['slug'] }}"
                                   @if ($loop->index >= 6) :class="all ? 'flex' : 'max-sm:hidden'" @endif
                                   class="flex min-h-12 w-full items-center gap-3 rounded-xl border border-gray-200 bg-white p-2.5 transition-[border-color,box-shadow] hover:border-gray-300 hover:shadow-sm sm:w-[168px] sm:shrink-0 sm:snap-start sm:flex-col sm:gap-2 sm:p-3 sm:text-center">
                                    <div class="flex shrink-0 items-center justify-center sm:h-[80px] sm:w-full">
                                        <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="size-8 shrink-0 grayscale sm:size-12" onerror="this.style.display='none'">
                                    </div>
                                    <span class="text-base font-bold leading-snug text-gray-900 sm:line-clamp-2 sm:text-sm">{{ $category['name'] }}</span>
                                </a>
                            @endforeach
                        </div>
                        @if (count($categories) > 6)
                            <button type="button" x-show="!all" @click="all = true" class="mt-2 flex min-h-12 w-full items-center justify-center gap-1 rounded-xl border border-green bg-white text-base font-bold text-dark-green hover:bg-green/5 sm:hidden">
                                Rodyti visas kategorijas ({{ count($categories) }})
                                <x-app-icon name="chevron-down" class="size-5" />
                            </button>
                        @endif
                    </div>
                </div>
            @endif


            {{-- Leaflets — same frame + card language as the rest of the page
                 (rounded-xl border, image on top, small store logo, minimal
                 text, whole card as one link) instead of <x-leaflet-card>'s
                 own heavier look (uppercase label, date range, full-width
                 button) — that design is right for /leidiniai itself, but
                 next to the comparison/deal cards here it read as a
                 different product. Same grid as the deals section above, no
                 carousel, for the same reason. --}}
            @if (count($latestLeaflets))
                <div>
                    <div class="section-heading-row">
                        <h2 class="section-heading-lg">Naujausi akcijų leidiniai</h2>
                        <a href="/leidiniai" class="section-link">
                            Žiūrėti visus
                            <x-app-icon name="chevron-right" class="size-4 opacity-80" />
                        </a>
                    </div>
                    {{-- Two to a row on phones, a 4-col grid at sm+. --}}
                    <div class="mt-4 flex flex-wrap gap-3 sm:grid sm:grid-cols-4">
                        @foreach ($latestLeaflets as $leaflet)
                            @php
                                $isExpired = $leaflet['status'] === 'expired';
                                $days = $leaflet['days_remaining'] ?? null;
                                $href = $leaflet['view_url'] ?? "/leidinys/{$leaflet['store_slug']}";
                            @endphp
                            <a href="{{ $href }}" class="flex w-[calc(50%-0.375rem)] flex-col gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 hover:opacity-80 sm:w-auto">
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
                                    <span class="text-sm font-semibold text-gray-500">{{ $leaflet['store_name'] }}</span>
                                </div>
                                <p class="line-clamp-2 text-[0.9em] font-semibold leading-tight text-gray-900">{{ $leaflet['title'] ?? $leaflet['store_name'] }}</p>
                                <span @class([
                                    'text-sm font-semibold',
                                    'text-gray-400' => $isExpired,
                                    'text-red-600' => !$isExpired && $days !== null && $days <= 2,
                                    'text-dark-green' => !$isExpired && ($days === null || $days > 2),
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
                {{-- 40 is a static number by explicit product decision, same
                     reasoning as the hero subtitle's "20 kitų parduotuvių" —
                     not derived from active_store_count. --}}
                <span><strong class="text-gray-900 tabular-nums">40</strong> parduotuvės su akcijomis ir leidiniais</span>
                @if ($stats['top_discount_percent'])
                    <span><strong class="text-gray-900 tabular-nums">{{ $stats['top_discount_percent'] }}%</strong> didžiausia nuolaida šiandien</span>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
