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
            @php
                // active_store_count is the TOTAL active-store count, which
                // already includes these 5 named ones — subtract them so
                // "ir dar N kitų" doesn't double-count and overstate real
                // coverage.
                $otherStoreCount = max(0, $stats['active_store_count'] - 5);
            @endphp
            <p class="max-w-xl text-base leading-snug text-gray-600 sm:text-lg">
                Ieškok bet kurios kasdienės prekės ir iškart palygink realias kainas Maxima, Lidl, Iki, Rimi, Norfa ir dar {{ $otherStoreCount }} kitų Lietuvos parduotuvių.
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
        <div class="base-container mx-auto flex flex-col gap-8 py-6 sm:gap-10 sm:py-10">

            {{-- Comparison teaser — the homepage's actual spine. Each category
                 shows its 2 best-covered items, cheapest-per-store only (no
                 "+N kiti" expansion here, that's what the full page is for). --}}
            @foreach ($comparisonCategories as $category)
                <div class="section-card">
                    <div class="section-heading-row">
                        <h2 class="section-heading">{{ $category['name'] }}</h2>
                        <a href="/pigiausios-prekes" class="section-link text-base">
                            Žiūrėti visas prekes
                            <x-app-icon name="chevron-right" class="size-4 opacity-80" />
                        </a>
                    </div>

                    @php
                        $unitLabel = fn (?string $basis) => match ($basis) {
                            'kg' => '€/kg',
                            'l' => '€/l',
                            '10vnt' => '€/10 vnt.',
                            default => null,
                        };
                    @endphp
                    <div class="mt-4 flex flex-col gap-5">
                        @foreach ($category['items'] as $item)
                            <div>
                                <p class="mb-2 text-base font-semibold text-gray-600">{{ $item['name'] }}</p>
                                {{-- Same shared card as /pigiausios-prekes (the real <x-deal-card>,
                                     not a bespoke one) — one cheapest match per store first (up
                                     to 5, one each); only when fewer than 5 stores currently
                                     carry this item does it backfill the rest of the row with
                                     next-cheapest matches (repeating a store if needed), so a
                                     row never looks half-empty just because one tracked store
                                     has no match today. 4 visible on mobile, 5 at lg+ (same
                                     "hide the extra one on mobile" trick already used in
                                     landing-deals-section). --}}
                                @php
                                    // Capped at 5 regardless of how many stores now carry a
                                    // match (up to 16 since the price index widened past the
                                    // old 5-store allowlist) — home is a teaser, not the full
                                    // comparison; that's what /pigiausios-prekes is for.
                                    $byStore = collect($item['by_store']);
                                    $topMatches = $byStore
                                        ->map(fn ($matches, $slug) => isset($matches[0]) ? ['slug' => $slug, 'match' => $matches[0]] : null)
                                        ->filter()
                                        ->sortBy('match.price')
                                        ->values();
                                    if ($topMatches->count() < 5) {
                                        $backfill = $byStore
                                            ->flatMap(fn ($matches, $slug) => collect($matches)->skip(1)->map(fn ($m) => ['slug' => $slug, 'match' => $m]))
                                            ->sortBy('match.price')
                                            ->values();
                                        $topMatches = $topMatches->concat($backfill);
                                    }
                                    $topMatches = $topMatches->take(5)->values();
                                @endphp
                                <div class="grid grid-cols-4 gap-3 lg:grid-cols-5">
                                    @foreach ($topMatches as $index => $row)
                                        @php $trackedStore = collect($stores)->firstWhere('slug', $row['slug']) ?? ['slug' => $row['slug'], 'name' => $row['slug']]; @endphp
                                        <div class="{{ $index >= 4 ? 'hidden lg:block' : '' }}">
                                            <x-price-compare-card
                                                :match="$row['match']"
                                                :store="$trackedStore"
                                                :unit-label="$unitLabel($item['unit_basis'])"
                                                :is-cheapest="$row['match']['price'] === $item['cheapest_price']"
                                                :highlight="false"
                                            />
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

            {{-- Curated deals — secondary to the comparison, browsing-oriented.
                 A compact custom card (same rounded-xl/gray-50/small-image
                 language as the comparison and leaflet cards) instead of
                 <x-deal-card> — that component's own size (generous padding,
                 22px price, favorite-heart overlay) is right for a dedicated
                 listing page, but next to the smaller comparison cards here
                 it read as oversized. --}}
            <div class="section-card">
                <div class="section-heading-row">
                    <h2 class="section-heading">Akcijos ir nuolaidos šiandien</h2>
                    <a href="/akcijos" class="section-link text-base">
                        Žiūrėti visas
                        <x-app-icon name="chevron-right" class="size-4 opacity-80" />
                    </a>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                    @foreach ($deals as $deal)
                        @php
                            $product = $deal['product'];
                            $discountPrice = (float) ($deal['discounted_price'] ?? 0);
                            $originalPrice = (float) ($deal['original_price'] ?? 0);
                            $showOriginal = $originalPrice > 0 && $originalPrice !== $discountPrice && $discountPrice > 0;
                            // Every store this product currently has a discount in, primary
                            // store (this deal's own store_id) first — same as <x-deal-card>.
                            $dealStores = collect($deal['offers'] ?? [])->pluck('store')->filter()->unique('slug')
                                ->sortBy(fn ($s) => ($s['id'] ?? null) === ($deal['store_id'] ?? null) ? 0 : 1)
                                ->values();
                        @endphp
                        <a href="/akcijos/{{ $product['full_slug'] }}" class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 hover:opacity-80">
                            <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-white">
                                @if ($product['image_url'])
                                    <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy" class="h-full w-full object-contain p-1.5">
                                @endif
                                @if ($discountPrice > 0)
                                    <span class="absolute bottom-1.5 left-1.5"><x-discount-badge :percent="$deal['discount_percent'] ?? null" size="sm" /></span>
                                @endif
                            </div>
                            <p class="line-clamp-2 text-[0.82em] leading-tight text-gray-700">{{ $product['name'] }}</p>
                            @if ($discountPrice > 0)
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-[1.05em] font-bold tabular-nums text-gray-900">{{ number_format($discountPrice, 2, ',', ' ') }}&nbsp;€</span>
                                    @if ($showOriginal)
                                        <span class="text-[0.75em] tabular-nums text-gray-400 line-through">{{ number_format($originalPrice, 2, ',', ' ') }}&nbsp;€</span>
                                    @endif
                                </div>
                            @elseif ($deal['discount_percent'] ?? null)
                                {{-- Some flyer-scraped offers (multi-variant packs) never get a
                                     clean per-item price, only a discount % — same fallback as
                                     <x-deal-card>, just not a 0,00 € price. --}}
                                <span class="inline-flex w-fit items-center rounded-md bg-[#ffdb4d] px-1.5 py-0.5 text-[0.78em] font-bold tabular-nums text-gray-900">
                                    Sutaupyk iki {{ (int) round($deal['discount_percent']) }}%
                                </span>
                            @endif
                            @if ($dealStores->count() > 0)
                                <div class="mt-auto flex items-center gap-1.5 pt-1">
                                    @foreach ($dealStores->take(3) as $storeItem)
                                        <x-store-logo :slug="$storeItem['slug']" :name="$storeItem['name']" size="xs" />
                                    @endforeach
                                    @if ($dealStores->count() > 3)
                                        <span class="text-[0.7em] font-medium text-gray-400">+{{ $dealStores->count() - 3 }}</span>
                                    @endif
                                </div>
                            @endif
                        </a>
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
