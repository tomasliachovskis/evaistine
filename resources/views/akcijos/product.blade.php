<?php
use App\Support\ProductPageMeta;
?>
@php
    $product = $primaryDeal['product'] ?? null;
    $isExpired = $primaryDeal['is_expired'] ?? false;
    $history = $primaryDeal['history'] ?? [];

    $allOffers = collect($offers ?? []);
    $activeOffers = $allOffers
        ->filter(fn ($offer) => ProductPageMeta::offerIsActive($offer['to_date'] ?? null))
        ->values();
    $expiredOffers = $allOffers
        ->reject(fn ($offer) => ProductPageMeta::offerIsActive($offer['to_date'] ?? null))
        ->values();
    $offers = $activeOffers->all();

    $primaryDealStillActive = ! $isExpired
        && ProductPageMeta::offerIsActive($primaryDeal['to_date'] ?? null)
        && (float) ($primaryDeal['discounted_price'] ?? $primaryDeal['min_price'] ?? 0) > 0;
    $isNoActivePromotion = $isExpired || ($activeOffers->isEmpty() && ! $primaryDealStillActive);

    if (! empty($offers)) {
        usort($offers, fn ($a, $b) => (float) ($a['discounted_price'] ?? PHP_INT_MAX) <=> (float) ($b['discounted_price'] ?? PHP_INT_MAX));
        $bestOffer = $offers[0];
    } elseif ($isNoActivePromotion) {
        $bestOffer = null;
    }

    $hasOffers = ! $isNoActivePromotion && count($offers) > 0;
    $hasAbout = $product && !empty(trim((string) ($product['description'] ?? '')));
    $hasSimilar = !empty($similar);
    $hasFaq = count($faqItems ?? []) > 0;

    // Same "current price counts as a history point too" fix the old
    // min/avg/max block had ($bestPrice isn't in $history — that table only
    // has past discount records, not the live current one). Computed up here
    // (not just before the section further down) so $hasHistory reflects
    // whether there's actually anything to show — some flyer-scraped offers
    // (multi-variant packs, "7 rūšių") only ever get a discount %, never a
    // clean per-item price, so every row here can filter out to nothing even
    // when $history itself has rows. Gating the "Kainų istorija" tab on the
    // raw row count instead of this filtered result showed a tab that led to
    // a blank section — confirmed on /akcijos/gyvunu-prekes/kaciu-sunu-dubeneliams,
    // whose single history row + single active discount both have no price,
    // discount_percent only.
    $priceHistoryPoints = collect($history)
        ->filter(fn ($h) => (float) ($h['discounted_price'] ?? 0) > 0 && !empty($h['from_date']) && !empty($h['store']['slug']))
        ->map(fn ($h) => [
            'store_slug' => $h['store']['slug'],
            'store_name' => $h['store']['name'],
            'price' => (float) $h['discounted_price'],
            'date' => $h['from_date'],
        ]);

    // Every store CURRENTLY selling it counts as a history point too, not
    // just the cheapest one — $offers isn't in $history (that table only has
    // past discount records), so without this a product on sale at 2 stores
    // right now only showed one of them in the chart/table. Always dated
    // today (not the discount's own from_date) so the chart visibly extends
    // to "now" for every current store, even one whose price hasn't changed
    // since a from_date that's already its own history point — otherwise
    // that store's line looked like it stopped weeks ago instead of still
    // being valid today.
    $today = now()->format('Y-m-d');
    collect($offers ?? [])
        ->filter(fn ($o) => (float) ($o['discounted_price'] ?? 0) > 0 && !empty($o['store']['slug']))
        ->each(function ($offer) use ($priceHistoryPoints, $today) {
            $priceHistoryPoints->push([
                'store_slug' => $offer['store']['slug'],
                'store_name' => $offer['store']['name'],
                'price' => (float) $offer['discounted_price'],
                'date' => $today,
            ]);
        });

    $priceHistoryPoints = $priceHistoryPoints->sortBy('date')->values();
    $hasHistory = $priceHistoryPoints->isNotEmpty();

    // product-page-tabs.tsx's tab list — was missing the "about" tab and every
    // tab's icon/shortLabel entirely.
    $tabs = array_filter([
        $hasOffers ? ['id' => 'offers', 'label' => 'Kainos parduotuvėse', 'shortLabel' => 'Kainos', 'icon' => 'shopping-bag'] : null,
        $hasSimilar ? ['id' => 'similar-products', 'label' => 'Panašūs produktai', 'shortLabel' => 'Panašūs produktai', 'icon' => 'layout-grid'] : null,
        $hasHistory ? ['id' => 'kainu-istorija', 'label' => 'Kainų istorija', 'shortLabel' => 'Istorija', 'icon' => 'clock'] : null,
        $hasAbout ? ['id' => 'about', 'label' => 'Apie produktą', 'shortLabel' => 'Apie', 'icon' => 'info'] : null,
        $hasFaq ? ['id' => 'faq', 'label' => 'DUK', 'shortLabel' => 'DUK', 'icon' => 'help-circle'] : null,
    ]);

    // PriceDealBadge in product-store-offers-section.tsx: "bad" tone gets an
    // amber box + Info icon, "good"/"neutral" both get a green box, but only
    // "good" gets the CircleCheck icon (neutral still shows Info).
    $dealSignalBox = [
        'good' => ['class' => 'bg-green/5', 'text' => 'text-green', 'icon' => 'circle-check', 'iconClass' => 'text-green'],
        'neutral' => ['class' => 'bg-green/5', 'text' => 'text-green', 'icon' => 'info', 'iconClass' => 'text-green'],
        'bad' => ['class' => 'bg-amber-50', 'text' => 'text-amber-900', 'icon' => 'info', 'iconClass' => 'text-amber-700'],
    ];

    // Grouped/sorted the same way buildStoreGroups does: one card per store
    // (cheapest offer if a store somehow has more than one), stores ordered
    // by ascending price.
    $offerGroups = collect($offers ?? [])
        ->groupBy(fn ($offer) => $offer['store']['slug'] ?? '')
        ->map(function ($storeOffers) {
            $sorted = $storeOffers->sortBy(fn ($o) => $o['discounted_price'] > 0 ? $o['discounted_price'] : PHP_INT_MAX)->values();

            return ['store' => $sorted[0]['store'], 'bestOffer' => $sorted[0]];
        })
        ->sortBy(fn ($group) => $group['bestOffer']['discounted_price'] > 0 ? $group['bestOffer']['discounted_price'] : PHP_INT_MAX)
        ->values();

    $noOffersDisplay = $isNoActivePromotion
        ? ProductPageMeta::buildNoOffersDisplay(
            collect($history)
                ->merge($expiredOffers)
                ->unique(fn ($offer) => ($offer['id'] ?? 0) . '|' . ($offer['store']['slug'] ?? ''))
                ->values()
                ->all()
        )
        : ['mode' => 'none', 'history' => [], 'best_offer' => null];
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($product['name'] ?? 'Produktas')"
    :description="$seo['meta_description'] ?? null"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($productSchema)
            <script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @if ($faqSchema)
            <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endpush

    <div class="base-container pb-1.5 pt-3">
        <nav class="flex flex-wrap items-center gap-1" aria-label="Naršymo kelias">
            @foreach ($breadcrumbs as $index => $crumb)
                @if ($index > 0)<x-app-icon name="arrow-right" class="size-3.5 text-gray-300" />@endif
                @php $isLastCrumb = $index === count($breadcrumbs) - 1; @endphp
                <a href="{{ $crumb['slug'] === '/' ? '/' : '/' . ltrim($crumb['slug'], '/') }}" class="text-sm transition-colors hover:text-green {{ $isLastCrumb ? 'font-medium text-green' : 'text-gray-600' }}">{{ $crumb['name'] }}</a>
            @endforeach
        </nav>
    </div>

    @if ($product)
        {{-- Hero, ported from product-hero.tsx: image/title grid, price, the
             "Sekti kainą" follow button (reuses the <x-favorite-button>
             widget already wired for the heart icon elsewhere) + deterministic
             per-product follower count (resolveProductFollowerCount in
             product-social-proof.ts), and the price-deal signal badge
             (resolvePriceDealSignal). --}}
        <div class="base-container pb-3 pt-1 sm:pt-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 max-lg:border-gray-100 sm:p-6">
                <div class="grid grid-cols-[128px_minmax(0,1fr)] items-start gap-x-4 gap-y-6 sm:grid-cols-[144px_minmax(0,1fr)] sm:gap-x-5 sm:gap-y-7 lg:grid-cols-[minmax(0,360px)_1fr] lg:gap-x-8">
                    <div class="flex min-w-0 items-start justify-center self-start overflow-hidden pt-2 pl-1.5 sm:pt-3 sm:pl-2 lg:p-3">
                        @if ($product['image_url'])
                            <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" class="h-auto max-h-[128px] w-full max-w-full origin-center scale-[1.2] object-contain sm:max-h-[144px] sm:scale-[1.15] lg:max-h-[190px] lg:scale-100">
                        @endif
                    </div>

                    <div class="flex min-w-0 flex-col gap-3">
                        <div>
                            @if ($product['brand'])
                                <span class="text-sm font-semibold uppercase text-gray-500">{{ $product['brand'] }}</span>
                            @endif
                            <h1 class="m-0 text-[1.35rem] font-semibold leading-[1.15] text-gray-900 sm:text-3xl lg:text-4xl">{{ ProductPageMeta::heroTitle($product['name'], $product['description'] ?? null) }}</h1>
                        </div>

                        @if ($isNoActivePromotion)
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Šiuo metu akcija nebegalioja. Žemiau matote paskutines žinomas kainas.</p>
                        @else
                            @if ($bestOffer)
                                @php
                                    $heroDiscountedPrice = (float) ($bestOffer['discounted_price'] ?? 0);
                                    // ActiveOffersPriceInfo in product-hero.tsx: no real price to
                                    // show at all (e.g. weighed produce, price varies by weight) —
                                    // falls back to a "Sutaupyk iki X%" block instead of "0,00 €".
                                    $heroPriceSlotPct = $heroDiscountedPrice <= 0 && !empty($bestOffer['discount_percent']) && $bestOffer['discount_percent'] > 0
                                        ? round($bestOffer['discount_percent'])
                                        : null;
                                @endphp
                                <div class="flex flex-col gap-1 py-2 sm:py-3">
                                    @if ($heroDiscountedPrice > 0)
                                        <div class="flex flex-wrap items-end gap-x-1.5">
                                            <span class="text-price-lg font-bold leading-none text-gray-900 lg:text-price-hero">{{ number_format($heroDiscountedPrice, 2, ',', ' ') }} €</span>
                                            <x-discount-badge :percent="$bestOffer['discount_percent'] ?? null" size="lg" />
                                            @if (!empty($bestOffer['original_price']) && $bestOffer['original_price'] > $heroDiscountedPrice)
                                                <del class="hidden text-xs font-medium tabular-nums text-gray-400 sm:text-sm lg:inline">{{ number_format($bestOffer['original_price'], 2, ',', ' ') }} €</del>
                                            @endif
                                        </div>
                                    @elseif ($heroPriceSlotPct !== null)
                                        <span class="inline-flex w-fit max-w-full items-center justify-center whitespace-nowrap rounded-lg bg-[#ffdb4d] px-2 py-1 text-price-lg font-bold leading-none tabular-nums text-gray-900 lg:text-price-hero">Sutaupyk iki {{ $heroPriceSlotPct }}%</span>
                                    @endif
                                </div>
                            @endif

                            {{-- resolveCardUrgencyLabel in landing-page-meta.ts: only shown when
                                 the deal expires within a day, not for every future end date. --}}
                            @if (!empty($primaryDeal['to_date']) && now()->diffInDays($primaryDeal['to_date'], false) <= 1)
                                <x-countdown :to-date="$primaryDeal['to_date']" />
                            @endif
                        @endif

                        {{-- Desktop: ProductPriceWatchBanner folds inline here, right
                             after price/countdown (or the expired notice), matching
                             product-hero.tsx's ActiveOffersHeroCard (hidden ... lg:flex). --}}
                        <div class="hidden lg:block">
                            <x-product-price-watch-banner
                                :product-id="$product['id']"
                                :favorited="\App\Support\FavoritedProducts::has($product['id'])"
                                :product-name="$product['name']"
                                :category-name="$product['category']['name'] ?? ''"
                                :variant="$isNoActivePromotion ? 'noOffers' : 'offersHero'"
                            />
                        </div>
                    </div>

                    {{-- Mobile/tablet: same banner, its own full-width row spanning both
                         grid columns below image+title (product-hero.tsx: col-span-2
                         col-start-1 row-start-2 ... lg:hidden) — a grid SIBLING of the
                         image/title columns, not nested inside the narrow title column,
                         which is what looked squeezed/collapsed there. --}}
                    <div class="col-span-2 pt-1 lg:hidden">
                        <x-product-price-watch-banner
                            :product-id="$product['id']"
                            :favorited="\App\Support\FavoritedProducts::has($product['id'])"
                            :product-name="$product['name']"
                            :category-name="$product['category']['name'] ?? ''"
                            :variant="$isNoActivePromotion ? 'noOffers' : 'offersHero'"
                        />
                    </div>
                </div>
            </div>
        </div>

        @if (count($tabs) > 1)
            {{-- Ported from product-page-tabs.tsx, with per-tab icons, a green
                 active-underline, and IntersectionObserver-driven active-tab
                 tracking as you scroll. Visible at every breakpoint — mobile
                 uses each tab's shortLabel + a tighter sticky offset (no
                 breadcrumb/hero-stats row above it like desktop has). --}}
            <nav
                class="sticky top-[calc(3.75rem+env(safe-area-inset-top,0px))] z-40 border-b border-gray-200 bg-white lg:top-[calc(6.25rem+env(safe-area-inset-top,0px))]"
                aria-label="Produkto skyriai"
                x-data="{
                    activeId: '{{ $tabs[array_key_first($tabs)]['id'] }}',
                    init() {
                        const ids = {{ json_encode(array_values(array_column($tabs, 'id'))) }};
                        const elements = ids.map((id) => document.getElementById(id)).filter(Boolean);
                        if (!elements.length) { return; }
                        const observer = new IntersectionObserver((entries) => {
                            const visible = entries
                                .filter((e) => e.isIntersecting)
                                .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
                            if (visible[0]?.target.id) { this.activeId = visible[0].target.id; }
                        }, { rootMargin: '-120px 0px -50% 0px', threshold: [0, 0.1, 0.25] });
                        elements.forEach((el) => observer.observe(el));
                    },
                }"
            >
                <div class="base-container">
                    <ul class="-mb-px flex gap-1 overflow-x-auto [scrollbar-width:none] sm:gap-2 [&::-webkit-scrollbar]:hidden">
                        @foreach ($tabs as $tab)
                            <li class="shrink-0">
                                <a
                                    href="#{{ $tab['id'] }}"
                                    aria-label="{{ $tab['label'] }}"
                                    :class="activeId === '{{ $tab['id'] }}' ? 'border-green text-dark-green' : 'border-transparent text-gray-600 hover:border-green/30 hover:text-dark-green'"
                                    class="inline-flex items-center gap-1.5 border-b-2 px-3 py-3 text-sm font-medium transition-colors sm:gap-2 sm:px-4"
                                >
                                    <x-app-icon :name="$tab['icon']" class="size-4 shrink-0 opacity-80" />
                                    <span class="whitespace-nowrap sm:hidden">{{ $tab['shortLabel'] }}</span>
                                    <span class="hidden whitespace-nowrap sm:inline">{{ $tab['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        @if ($hasOffers)
            <section id="offers" class="base-container scroll-mt-32 pb-6 pt-3 sm:pt-4 lg:pt-5">
                <h2 class="mb-1 text-lg font-bold text-gray-900">{{ \App\Support\ProductPageMeta::offersHeading() }}</h2>
                <p class="mb-4 text-sm text-gray-500">Palyginome {{ $offerGroups->count() }} {{ $offerGroups->count() === 1 ? 'parduotuvės pasiūlymą' : 'parduotuvių pasiūlymus' }}.</p>

                {{-- Ported from product-store-offer-card.tsx (via
                     ProductStoreOffersSection/buildStoreGroups) — one card per
                     store, not a table: logo, price + yellow corner discount
                     badge, an "Iki MM.DD" pill top-right when the offer has an
                     end date, and an origin line ("{Store} parduotuvė" for the
                     5 stores with real online prices, "{Store} kainų leidinys"
                     for the rest, scraped from a leaflet). --}}
                <div class="flex w-full flex-col gap-3 sm:gap-4">
                    @foreach ($offerGroups as $index => $group)
                        <x-product-store-offer-card
                            :store="$group['store']"
                            :offer="$group['bestOffer']"
                            :show-best-price-badge="$offerGroups->count() > 1 && $index === 0"
                        />
                    @endforeach
                </div>

                @if ($priceDealSignal)
                    @php $box = $dealSignalBox[$priceDealSignal['tone']] ?? $dealSignalBox['neutral']; @endphp
                    <div class="mt-3 inline-flex w-fit max-w-full items-start gap-2.5 rounded-lg px-3 py-3 sm:mt-4 sm:gap-3 sm:px-4 sm:py-3.5 {{ $box['class'] }}">
                        <x-app-icon :name="$box['icon']" class="mt-0.5 size-5 shrink-0 {{ $box['iconClass'] }}" />
                        <p class="text-sm font-semibold leading-snug sm:text-base {{ $box['text'] }}">
                            {{ $priceDealSignal['label'] }} {{ $priceDealSignal['description'] }}
                        </p>
                    </div>
                @endif
            </section>
        @elseif ($isNoActivePromotion && ($noOffersDisplay['mode'] ?? 'none') === 'priced')
            <section class="base-container pb-6 pt-5 sm:pt-6 lg:pt-8">
                <h2 class="mb-4 text-lg font-bold text-gray-900">
                    {{ count($noOffersDisplay['history']) === 1 ? 'Paskutinė žinoma kaina' : 'Paskutinės žinomos kainos' }}
                </h2>
                <div class="flex w-full flex-col gap-3 sm:gap-4" style="filter: grayscale(0.55) saturate(0.7)">
                    @foreach ($noOffersDisplay['history'] as $offer)
                        <x-product-store-offer-card
                            :store="$offer['store']"
                            :offer="$offer"
                        />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($hasSimilar)
            <section id="similar-products" class="scroll-mt-32 border-t border-gray-200 bg-white pb-6 pt-5 sm:py-8">
                <div class="base-container">
                    <h2 class="mb-4 text-lg font-bold text-gray-900">{{ \App\Support\ProductPageMeta::similarHeading($product) }}</h2>
                    {{-- Max 2 rows on every breakpoint: 2 cols on mobile (4 items),
                         5 cols from sm+ (10 items) — items past the 4th are fetched
                         (up to 10, see ProductController::getProductWithSimilar's
                         $similarLimit) but hidden below sm so mobile still only shows
                         its first 2 rows. --}}
                    <div class="grid w-full grid-cols-2 items-stretch gap-1.5 sm:grid-cols-5 sm:gap-2 lg:gap-3">
                        @foreach ($similar as $deal)
                            <div class="{{ $loop->index >= 4 ? 'hidden sm:block' : '' }}">
                                <x-deal-card :deal="$deal" class="h-full" source="similar_products" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($hasHistory)
            <section id="kainu-istorija" class="scroll-mt-32 border-t border-gray-200 bg-white py-6 sm:py-8">
                <div class="base-container">
                    <x-price-history-chart :points="$priceHistoryPoints" :product-name="$product['name']" />
                </div>
            </section>
        @endif

        @if ($hasAbout)
            <section id="about" class="border-t border-gray-200 bg-white py-6 sm:py-8">
                <div class="base-container max-w-3xl">
                    <h2 class="mb-3 text-lg font-bold text-gray-900">Apie prekę</h2>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ $product['description'] }}</p>
                </div>
            </section>
        @endif

        @if ($hasFaq)
            <section id="faq" class="border-t border-gray-200 bg-white py-8 sm:py-10">
                {{-- listing-faq.tsx's default layout="grid" — full base-container
                     width, 2 columns from md: up, not the narrow single column
                     (with a stray max-w-3xl) we had before. --}}
                <div class="base-container">
                    <h2 class="mb-4 text-lg font-bold text-gray-900">Dažniausiai užduodami klausimai</h2>
                    <x-faq-accordion :items="$faqItems" />
                </div>
            </section>
        @endif
    @endif
</x-layouts.app>
