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

    // MOCKUP (idea #10, not yet a permanent feature): when this product has
    // no active discount, feature up to 3 cheapest currently-discounted
    // "generic siblings" (AkcijosController passes this from
    // ProductController::activeGenericAlternatives — same real-world item
    // across stores/pack sizes, matched via generic_product_id) instead of
    // loosely-related $similar entries from the same broad category (that
    // surfaced blueberries on a cucumber page — same category, not the same
    // product). Mobile shows just the first one with an explicit button
    // (clearer tap target for an older audience); desktop has room to show
    // all of them as directly clickable cards instead.
    $genericAlternatives = $isNoActivePromotion ? ($genericAlternatives ?? []) : [];
    $bestAlternative = $genericAlternatives[0] ?? null;

    // Same "current price counts as a history point too" fix the old
    // min/avg/max block had ($bestPrice isn't in $history — that table only
    // has past discount records, not the live current one). Computed up here
    // (not just before the section further down) so $hasHistory reflects
    // whether there's actually anything to show — some flyer-scraped offers
    // (multi-variant packs, "7 rūšių") only ever get a discount %, never a
    // clean per-item price, so every row here can filter out to nothing even
    // when $history itself has rows. Gating the "Kainų istorija" tab on the
    // raw row count instead of this filtered result showed a tab that led to
    // a blank section — confirmed on /gyvunu-prekes/kaciu-sunu-dubeneliams,
    // whose single history row + single active discount both have no price,
    // discount_percent only.
    // ~36% of discount_histories rows only ever recorded an end_at (some
    // stores' scraped listings only expose a validity end date, never a
    // start date — same root cause as ProcessDiscounts' start_at/end_at
    // gotcha for the live discounts table). Requiring from_date here hid
    // the whole chart/tab for a huge share of expired products even though
    // buildNoOffersDisplay()'s "last known prices" list below tolerates the
    // same rows fine (it never required from_date at all) — fall back to
    // to_date so those rows still plot, just anchored to when we stopped
    // seeing that price instead of when it started.
    $priceHistoryPoints = collect($history)
        ->filter(fn ($h) => (float) ($h['discounted_price'] ?? 0) > 0 && (!empty($h['from_date']) || !empty($h['to_date'])) && !empty($h['store']['slug']))
        ->map(fn ($h) => [
            'store_slug' => $h['store']['slug'],
            'store_name' => $h['store']['name'],
            'price' => (float) $h['discounted_price'],
            'date' => $h['from_date'] ?? $h['to_date'],
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
        $hasOffers ? ['id' => 'offers', 'label' => 'Kainos vaistinėse', 'shortLabel' => 'Kainos', 'icon' => 'shopping-bag'] : null,
        $hasSimilar ? ['id' => 'similar-products', 'label' => 'Panašūs produktai', 'shortLabel' => 'Panašūs', 'icon' => 'layout-grid'] : null,
        $hasHistory ? ['id' => 'kainu-istorija', 'label' => 'Kainų istorija', 'shortLabel' => 'Istorija', 'icon' => 'clock'] : null,
        $hasAbout ? ['id' => 'about', 'label' => 'Apie produktą', 'shortLabel' => 'Apie', 'icon' => 'info'] : null,
        $hasFaq ? ['id' => 'faq', 'label' => 'DUK', 'shortLabel' => 'DUK', 'icon' => 'help-circle'] : null,
    ]);

    // PriceDealBadge in product-store-offers-section.tsx: "bad" tone gets an
    // amber box + Info icon, "good"/"neutral" both get a green box, but only
    // "good" gets the CircleCheck icon (neutral still shows Info).
    $dealSignalBox = [
        'good' => ['class' => 'bg-green/5', 'text' => 'text-dark-green', 'icon' => 'circle-check', 'iconClass' => 'text-dark-green'],
        'neutral' => ['class' => 'bg-green/5', 'text' => 'text-dark-green', 'icon' => 'info', 'iconClass' => 'text-dark-green'],
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

@endphp

<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []"
    :title="$seo['meta_title'] ?? ($product['name'] ?? 'Produktas')"
    :description="$seo['meta_description'] ?? null"
    :canonical="$canonical"
    :robots="$robots"
    :og-image="$product['image_url'] ?? null"
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

    <div class="base-container pb-2 pt-3 lg:pb-4">
        <nav class="flex flex-wrap items-center gap-x-2" aria-label="Naršymo kelias">
            @foreach ($breadcrumbs as $index => $crumb)
                @if ($index > 0)<x-app-icon name="arrow-right" class="crumb-sep" />@endif
                @php $isLastCrumb = $index === count($breadcrumbs) - 1; @endphp
                <a href="{{ $crumb['slug'] === '/' ? '/' : '/' . ltrim($crumb['slug'], '/') }}" class="crumb-link {{ $isLastCrumb ? 'crumb-current' : '' }}">{{ $crumb['name'] }}</a>
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
        <div class="base-container pb-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 max-lg:border-gray-100 sm:p-6">
                {{-- Phones: one column, the photo full width on top and the title,
                     price and store under it (a 128px photo column squeezed both). --}}
                <div class="grid grid-cols-1 items-start gap-x-4 gap-y-4 sm:grid-cols-[144px_minmax(0,1fr)] sm:gap-x-5 sm:gap-y-7 lg:grid-cols-[minmax(0,360px)_1fr] lg:gap-x-8">
                    <div class="flex min-w-0 items-start justify-center self-start overflow-hidden sm:pt-3 sm:pl-2 lg:p-3">
                        @if ($product['image_url'])
                            <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" fetchpriority="high" loading="eager" class="aspect-square h-auto max-h-48 w-full max-w-full origin-center object-contain sm:max-h-[144px] sm:scale-[1.15] {{ $bestAlternative ? 'lg:max-h-[320px]' : 'lg:max-h-[190px]' }} lg:scale-100">
                        @endif
                    </div>

                    <div class="flex min-w-0 flex-col gap-3">
                        <div>
                            @if ($product['brand'])
                                <span class="text-sm font-semibold uppercase text-gray-500">{{ $product['brand'] }}</span>
                            @endif
                            <h1 class="m-0 text-xl font-semibold leading-tight text-gray-900 sm:text-2xl lg:text-3xl">{{ ProductPageMeta::heroTitle($product['name'], $product['description'] ?? null) }}</h1>
                        </div>

                        @if ($isNoActivePromotion)
                            <p class="rounded-lg bg-amber-50 px-3 py-2.5 text-base text-amber-800">Akcija nebegalioja.</p>

                            {{-- Desktop keeps the original nested-in-text-column placement
                                 (narrower, next to the product photo) — only the lg:hidden
                                 col-span-2 copy further down goes full-card-width, since below
                                 lg that's where the dead space next to the (now much smaller)
                                 image column was. --}}
                            @if ($bestAlternative)
                                <div class="mt-1 hidden w-full rounded-2xl border border-green/30 bg-green/5 p-4 lg:block">
                                    <p class="text-base font-bold text-dark-green">Radome panašų produktą su aktyvia nuolaida:</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2.5 lg:grid-cols-3">
                                        @foreach ($genericAlternatives as $alt)
                                            @php
                                                $altP = $alt['product'];
                                                $altPHref = '/' . $altP['full_slug'];
                                                $altPPrice = (float) ($alt['discounted_price'] ?? 0);
                                                $altPStore = collect($alt['offers'] ?? [])->pluck('store')->filter()->first();
                                            @endphp
                                            <a href="{{ $altPHref }}" data-ga-event="product_card_click" data-ga-product-id="{{ $altP['id'] }}" data-ga-product-name="{{ $altP['name'] }}" data-ga-source="alternative"
                                               class="flex items-center gap-2.5 rounded-xl bg-white p-2.5 transition-colors hover:bg-gray-50">
                                                <div class="relative aspect-square w-20 shrink-0 overflow-hidden rounded-lg bg-white">
                                                    @if ($altP['image_url'])
                                                        <img src="{{ $altP['image_url'] }}" alt="{{ $altP['name'] }}" loading="lazy" class="h-full w-full object-contain p-1.5">
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="line-clamp-2 text-sm font-medium leading-snug text-gray-900">{{ $altP['name'] }}</p>
                                                    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                                        @if ($altPPrice > 0)
                                                            <span class="text-base font-bold tabular-nums text-gray-900">{{ number_format($altPPrice, 2, ',', ' ') }} €</span>
                                                        @endif
                                                        @if (!empty($alt['discount_percent']))
                                                            <x-discount-badge :percent="$alt['discount_percent']" size="sm" />
                                                        @endif
                                                    </div>
                                                    @if ($altPStore)
                                                        <div class="mt-1">
                                                            <x-store-logo :slug="$altPStore['slug']" :name="$altPStore['name']" size="xs" />
                                                        </div>
                                                    @endif
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
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
                                {{-- Price, then "Įprasta kaina" spelled out (a bare
                                     struck-through number next to the badge read as clutter),
                                     then one boxed row saying where and until when. --}}
                                <div class="flex flex-col gap-3 py-1 sm:py-2">
                                    @if ($heroDiscountedPrice > 0)
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <span class="text-price-lg font-bold leading-none text-gray-900 lg:text-price-hero">{{ number_format($heroDiscountedPrice, 2, ',', ' ') }} €</span>
                                                <x-discount-badge :percent="$bestOffer['discount_percent'] ?? null" size="lg" />
                                            </div>
                                            @if (!empty($bestOffer['original_price']) && $bestOffer['original_price'] > $heroDiscountedPrice)
                                                <p class="text-base text-gray-600">Įprasta kaina <del class="tabular-nums">{{ number_format($bestOffer['original_price'], 2, ',', ' ') }} €</del></p>
                                            @endif
                                        </div>
                                    @elseif ($heroPriceSlotPct !== null)
                                        <span class="inline-flex w-fit max-w-full items-center justify-center rounded-lg bg-deal px-2 py-1 text-xl font-bold leading-none tabular-nums text-deal-foreground sm:text-2xl">Sutaupyk iki {{ $heroPriceSlotPct }}%</span>
                                    @endif
                                    {{-- Where and until when, right under the price: older
                                         readers otherwise had to scroll to "Kainos
                                         vaistinėse" to learn which shop the price is from. --}}
                                    @if (!empty($bestOffer['store']['slug']))
                                        @php
                                            $heroValidity = \App\Support\ProductPageMeta::validUntilLabel($bestOffer['to_date'] ?? null);
                                            $heroOtherStores = $offerGroups->count() - 1;
                                        @endphp
                                        <div class="flex flex-col items-start gap-2">
                                            <div class="inline-flex max-w-full items-center gap-3 rounded-xl bg-gray-50 px-3 py-2">
                                                <x-store-logo :slug="$bestOffer['store']['slug']" :name="$bestOffer['store']['name'] ?? ''" size="sm" class="shrink-0" />
                                                @if ($heroValidity)
                                                    <span class="h-6 w-px shrink-0 bg-gray-300"></span>
                                                    <span class="min-w-0 text-base leading-snug text-gray-700">Galioja {{ lcfirst($heroValidity) }}</span>
                                                @endif
                                            </div>
                                            @if ($heroOtherStores > 0)
                                                <a href="#offers" class="-my-2 inline-flex min-h-12 items-center text-base font-semibold text-dark-green underline underline-offset-4">Dar {{ $heroOtherStores }} {{ \App\Support\LithuanianPlural::storeWord($heroOtherStores) }} – palyginti kainas</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
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
                                :product-image="$product['image_url'] ?? null"
                                :category-name="$product['category']['name'] ?? ''"
                                :variant="$isNoActivePromotion ? 'noOffers' : 'offersHero'"
                            />
                        </div>
                    </div>

                    {{-- Mobile/tablet: same banner, its own full-width row spanning both
                         grid columns (a grid SIBLING of the image/title columns, not nested
                         in the narrow title column, which looked squeezed). Right under the
                         price, before the alternative card: after that card it sat low on
                         phones, under the cookie banner and near the bottom nav (presses
                         fell from ~20 to 3 a day, 2026-10-02). --}}
                    <div class="col-span-full pt-1 lg:hidden">
                        <x-product-price-watch-banner
                            :product-id="$product['id']"
                            :favorited="\App\Support\FavoritedProducts::has($product['id'])"
                            :product-name="$product['name']"
                            :product-image="$product['image_url'] ?? null"
                            :category-name="$product['category']['name'] ?? ''"
                            :variant="$isNoActivePromotion ? 'noOffers' : 'offersHero'"
                        />
                    </div>

                    {{-- MOCKUP (idea #10), redesigned for an older (50-60+) audience — below
                         lg only (lg:hidden): a grid SIBLING of the image/title columns
                         (col-span-2, same pattern as the price-watch banner below), not nested
                         inside the narrow text column, which left a wide dead gap next to the
                         product photo at those widths. At lg+, the nested hidden-lg:block copy
                         above (next to the photo, narrower) is used instead. Every alternative
                         is its own directly clickable card (no separate button) — always at
                         least 2 up even on narrow phones (max 2 shown there), 3 from lg. --}}
                    @if ($bestAlternative)
                        <div class="col-span-full rounded-2xl border border-green/30 bg-green/5 p-4 lg:hidden">
                            <p class="text-base font-bold text-dark-green">Radome panašų produktą su aktyvia nuolaida:</p>

                            <div class="mt-3 grid grid-cols-2 gap-2.5 lg:grid-cols-3">
                                @foreach ($genericAlternatives as $alt)
                                    @php
                                        $altP = $alt['product'];
                                        $altPHref = '/' . $altP['full_slug'];
                                        $altPPrice = (float) ($alt['discounted_price'] ?? 0);
                                        $altPStore = collect($alt['offers'] ?? [])->pluck('store')->filter()->first();
                                    @endphp
                                    {{-- Stacked (image on top) below lg, not the
                                         side-by-side row the desktop card
                                         (above, hidden here) uses — at 2-up on
                                         a phone-width grid cell, an 80px image
                                         next to text left almost no room for
                                         the name/price, truncating badly
                                         (confirmed live 2026-09-20: "Vaisiu
                                         sk....", price wrapping mid-number).
                                         lg+ still gets the compact row shape,
                                         where 3-up leaves enough width. --}}
                                    <a href="{{ $altPHref }}" data-ga-event="product_card_click" data-ga-product-id="{{ $altP['id'] }}" data-ga-product-name="{{ $altP['name'] }}" data-ga-source="alternative"
                                       class="{{ $loop->index >= 2 ? 'hidden lg:flex' : 'flex' }} flex-col items-stretch gap-2 rounded-xl bg-white p-2.5 transition-colors hover:bg-gray-50 lg:flex-row lg:items-center lg:gap-2.5">
                                        <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-white lg:w-20 lg:shrink-0">
                                            @if ($altP['image_url'])
                                                <img src="{{ $altP['image_url'] }}" alt="{{ $altP['name'] }}" loading="lazy" class="h-full w-full object-contain p-1.5">
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="line-clamp-2 text-sm font-medium leading-snug text-gray-900">{{ $altP['name'] }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                                @if ($altPPrice > 0)
                                                    <span class="text-base font-bold tabular-nums text-gray-900">{{ number_format($altPPrice, 2, ',', ' ') }} €</span>
                                                @endif
                                                @if (!empty($alt['discount_percent']))
                                                    <x-discount-badge :percent="$alt['discount_percent']" size="sm" />
                                                @endif
                                            </div>
                                            @if ($altPStore)
                                                <div class="mt-1">
                                                    <x-store-logo :slug="$altPStore['slug']" :name="$altPStore['name']" size="xs" />
                                                </div>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>

                <x-product-notice
                    :category-slug="$product['category']['slug'] ?? config('categories.roots')[$product['category']['name'] ?? ''] ?? null"
                    class="mt-4 sm:mt-5"
                />
            </div>
        </div>

        @if (count($tabs) > 1)
            {{-- Ported from product-page-tabs.tsx, with per-tab icons, a green
                 active-underline, and IntersectionObserver-driven active-tab
                 tracking as you scroll. Visible at every breakpoint — mobile
                 uses each tab's shortLabel + a tighter sticky offset (no
                 breadcrumb/hero-stats row above it like desktop has). --}}
            <nav
                class="sticky top-[calc(var(--header-h)+env(safe-area-inset-top,0px))] z-40 border-b border-gray-200 bg-white"
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
                    <ul class="-mb-px flex justify-between gap-0.5 overflow-x-auto sm:justify-start [scrollbar-width:none] sm:gap-2 [&::-webkit-scrollbar]:hidden">
                        @foreach ($tabs as $tab)
                            <li class="shrink-0">
                                <a
                                    href="#{{ $tab['id'] }}"
                                    aria-label="{{ $tab['label'] }}"
                                    data-ga-event="product_tab_click"
                                    data-ga-source="{{ $tab['id'] }}"
                                    :class="activeId === '{{ $tab['id'] }}' ? 'border-green text-dark-green' : 'border-transparent text-gray-600 hover:border-green/30 hover:text-dark-green'"
                                    class="inline-flex min-h-12 items-center gap-1.5 border-b-2 px-2.5 text-sm font-medium transition-colors sm:gap-2 sm:px-4"
                                >
                                    <x-app-icon :name="$tab['icon']" class="hidden size-4 shrink-0 opacity-80 sm:block" />
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
            <section id="offers" class="base-container scroll-mt-40 pb-6 pt-3 sm:pt-4 lg:pt-5">
                <h2 class="mb-1 text-lg font-bold text-gray-900">{{ \App\Support\ProductPageMeta::offersHeading() }}</h2>
                <p class="mb-4 text-sm text-gray-500">Palyginome {{ $offerGroups->count() }} {{ $offerGroups->count() === 1 ? 'vaistinės pasiūlymą' : 'vaistinių pasiūlymus' }}. Pirksite pasirinktos vaistinės svetainėje, už prekę, kainą ir pristatymą atsako vaistinė.</p>

                {{-- Ported from product-store-offer-card.tsx (via
                     ProductStoreOffersSection/buildStoreGroups) — one card per
                     store, not a table: logo, price + yellow corner discount
                     badge, an "Iki MM.DD" pill top-right when the offer has an
                     end date, and an origin line ("{Store} vaistinė" for the
                     5 stores with real online prices, "{Store} kainų leidinys"
                     for the rest, scraped from a leaflet). --}}
                <div class="flex w-full flex-col gap-3 sm:gap-4">
                    @foreach ($offerGroups as $index => $group)
                        <x-product-store-offer-card
                            :store="$group['store']"
                            :offer="$group['bestOffer']"
                            :show-best-price-badge="$offerGroups->count() > 1 && $index === 0"
                            :flyer-link="($flyerLinks ?? collect())->get($group['bestOffer']['id'] ?? null)"
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
        @endif

        @guest
            {{-- Without the offers section above, the card would sit right
                 against the sticky tabs, so it brings its own top gap then. --}}
            <div class="base-container pb-6 {{ $hasOffers ? '' : 'pt-5 sm:pt-6' }}">
                <x-signup-inline-card />
            </div>
        @endguest

        {{-- Panašūs produktai (live, potentially still-active deals) comes
             before the stale "Paskutinės žinomos kainos" history list below —
             when there's no active promotion, a concrete current alternative
             is more actionable than a greyed-out past-price list, so it
             should be seen first, not after. --}}
        @if ($hasSimilar)
            <section id="similar-products" class="scroll-mt-40 border-t border-gray-200 bg-white pb-6 pt-5 sm:py-8">
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

        {{-- "Paskutinės žinomos kainos" (a plain list of past store prices)
             used to render here for no-active-promotion products — dropped in
             favor of the "Kainų istorija" chart just below, which already
             covers the same ground with less repetition. --}}

        @if ($hasHistory)
            <section id="kainu-istorija" class="scroll-mt-40 border-t border-gray-200 bg-white py-6 sm:py-8">
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

        @if (!empty($relatedKeywordPages))
            {{-- Real cross-links from this one product to whichever keyword
                 pages it actually belongs to (KeywordPageService::
                 relatedPagesForProduct(), precomputed via keywords:map-
                 products) — the one internal-link direction product pages
                 never had before. --}}
            <section id="related-akcijos" class="border-t border-gray-200 bg-white py-6 sm:py-8">
                <div class="base-container">
                    <h2 class="mb-3 text-lg font-bold text-gray-900">Susijusios akcijos</h2>
                    @include('components.partials.related-keyword-links', ['links' => $relatedKeywordPages])
                </div>
            </section>
        @endif
    @endif
</x-layouts.app>
