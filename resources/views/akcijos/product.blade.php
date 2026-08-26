<?php
use App\Support\ProductPageMeta;
?>
@php
    $product = $primaryDeal['product'] ?? null;
    $isExpired = $primaryDeal['is_expired'] ?? false;
    $history = $primaryDeal['history'] ?? [];

    $hasOffers = !$isExpired && count($offers) > 0;
    $hasHistory = count($history) > 0;
    $hasAbout = $product && !empty(trim((string) ($product['description'] ?? '')));
    $hasSimilar = !empty($similar);
    $hasFaq = count($faqItems ?? []) > 0;

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
        <div class="base-container pb-6 pt-1 sm:pt-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-3 max-lg:border-gray-100 sm:p-6 lg:p-5">
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

                        @if ($isExpired)
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Šiuo metu akcija nebegalioja. Žemiau matote paskutines žinomas kainas.</p>
                        @else
                            @if ($bestOffer)
                                <div class="flex flex-col gap-1 py-2 sm:py-3">
                                    <div class="flex flex-wrap items-end gap-x-1.5">
                                        <span class="text-[1.75rem] font-bold leading-none text-gray-900 lg:text-[2rem]">{{ number_format($bestOffer['discounted_price'], 2, ',', ' ') }} €</span>
                                        {{-- MIN_PROMOTION_BADGE_PERCENT in promotion-percent-badge.tsx. --}}
                                        @if (!empty($bestOffer['discount_percent']) && $bestOffer['discount_percent'] >= 20)
                                            <span class="inline-flex h-[1.6rem] items-center rounded-lg bg-[#ffdb4d] px-2.5 text-[17.6px] font-bold leading-none tabular-nums text-gray-900 sm:h-[2rem] sm:px-3 sm:text-[19px]">-{{ round($bestOffer['discount_percent']) }}%</span>
                                        @endif
                                        @if (!empty($bestOffer['original_price']) && $bestOffer['original_price'] > $bestOffer['discounted_price'])
                                            <del class="hidden text-[0.8rem] font-medium tabular-nums text-gray-400 sm:text-[1rem] lg:inline">{{ number_format($bestOffer['original_price'], 2, ',', ' ') }} €</del>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- resolveCardUrgencyLabel in landing-page-meta.ts: only shown when
                                 the deal expires within a day, not for every future end date. --}}
                            @if (!empty($primaryDeal['to_date']) && now()->diffInDays($primaryDeal['to_date'], false) <= 1)
                                <x-countdown :to-date="$primaryDeal['to_date']" />
                            @endif

                            {{-- Desktop: ProductPriceWatchBanner folds inline here, right
                                 after price/countdown, matching product-hero.tsx's
                                 ActiveOffersHeroCard (hidden ... lg:flex). The component
                                 itself already swaps its own mobile/desktop button
                                 internally — the outer div here just decides WHERE it
                                 renders per breakpoint. Rendering it only here at every
                                 breakpoint (previous attempt) put it on its own grid row
                                 even at lg+, showing as a stray gap below the price. --}}
                            <div class="hidden lg:block">
                                <x-product-price-watch-banner :product-id="$product['id']" :favorited="\App\Support\FavoritedProducts::has($product['id'])" />
                            </div>
                        @endif
                    </div>

                    {{-- Mobile/tablet: same banner, its own full-width row spanning both
                         grid columns below image+title (product-hero.tsx: col-span-2
                         col-start-1 row-start-2 ... lg:hidden) — a grid SIBLING of the
                         image/title columns, not nested inside the narrow title column,
                         which is what looked squeezed/collapsed there. --}}
                    @if (!$isExpired)
                        <div class="col-span-2 pt-1 lg:hidden">
                            <x-product-price-watch-banner :product-id="$product['id']" :favorited="\App\Support\FavoritedProducts::has($product['id'])" />
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (count($tabs) > 1)
            {{-- Ported from product-page-tabs.tsx — desktop-only (hidden below
                 lg:), with per-tab icons, a green active-underline, and
                 IntersectionObserver-driven active-tab tracking as you scroll.
                 Was previously visible on mobile too, with no icons and no
                 active state at all. --}}
            <nav
                class="sticky top-[calc(6.25rem+env(safe-area-inset-top,0px))] z-40 hidden border-b border-gray-200 bg-white lg:block"
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
            <section id="offers" class="base-container scroll-mt-32 pb-6 pt-5 sm:pt-6 lg:pt-8">
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
                        @php
                            $offer = $group['bestOffer'];
                            $store = $group['store'];
                            $showBestPriceBadge = $offerGroups->count() > 1 && $index === 0;
                            $validityLabel = \App\Support\ProductPageMeta::validUntilLabel($offer['to_date'] ?? null);
                            $pct = \App\Support\ProductPageMeta::promotionBadgePercent($offer['discount_percent'] ?? null);
                            $showOriginal = !empty($offer['original_price']) && !empty($offer['discounted_price']) && $offer['original_price'] > $offer['discounted_price'];
                        @endphp
                        <a
                            href="/akcijos/{{ $store['slug'] ?? '' }}"
                            class="relative flex w-full rounded-xl border border-green/35 bg-white p-4 transition-colors hover:border-green/45 sm:p-5 {{ $showBestPriceBadge ? 'pt-6 sm:pt-7' : '' }} {{ $validityLabel ? 'pr-24 sm:pr-28' : '' }}"
                        >
                            @if ($showBestPriceBadge)
                                <span class="absolute left-3 top-0 z-10 inline-flex -translate-y-1/2 items-center rounded-full bg-green px-3 py-1 text-[11px] font-bold leading-none text-white sm:left-3.5 sm:px-3.5 sm:text-xs">
                                    <x-app-icon name="star" class="mr-1 size-3" fill="currentColor" />
                                    Geriausia kaina
                                </span>
                            @endif
                            @if ($validityLabel)
                                <span class="absolute right-3 top-3 rounded-full bg-[#e8eef3] px-3 py-1 text-xs font-medium text-gray-700 sm:right-4 sm:top-4">{{ $validityLabel }}</span>
                            @endif
                            <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                                <div class="flex h-9 w-14 shrink-0 items-center justify-center sm:h-11 sm:w-[4.75rem]">
                                    <img src="/assets/stores/{{ $store['slug'] ?? '' }}.svg" alt="" class="max-h-full max-w-full object-contain">
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-1.5 sm:gap-2">
                                    <div class="flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1">
                                        <span class="shrink-0 text-xl font-bold tabular-nums text-gray-900 sm:text-2xl">{{ number_format($offer['discounted_price'], 2, ',', ' ') }} €</span>
                                        @if ($pct !== null)
                                            <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#ffdb4d] px-2 py-1 text-[13px] font-bold leading-none text-gray-900 tabular-nums">-{{ $pct }}%</span>
                                        @endif
                                        @if ($showOriginal)
                                            <del class="hidden min-w-0 truncate text-xs font-medium tabular-nums text-gray-400 sm:inline sm:text-sm">{{ number_format($offer['original_price'], 2, ',', ' ') }} €</del>
                                        @endif
                                    </div>
                                    <div class="inline-flex min-w-0 items-center gap-1.5 text-xs font-normal leading-snug text-gray-500 sm:text-[13px]">
                                        <x-app-icon name="info" class="size-3.5 shrink-0 text-gray-400 sm:size-4" />
                                        <span class="min-w-0">{{ \App\Support\ProductPageMeta::offerOriginLabel($store['slug'] ?? '', $store['name'] ?? '') }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
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
        @elseif ($isExpired)
            <section class="base-container pb-6 pt-5 sm:pt-6 lg:pt-8">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Paskutinės žinomos kainos</h2>
                <div class="space-y-2">
                    @foreach ($history as $offer)
                        <div class="flex items-center justify-between rounded-2xl border border-gray-100 px-4 py-3">
                            <span class="font-semibold text-gray-900">{{ $offer['store']['name'] ?? '' }}</span>
                            <span class="text-lg font-bold text-gray-700">{{ number_format($offer['discounted_price'], 2, ',', ' ') }} €</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($hasSimilar)
            <section id="similar-products" class="scroll-mt-32 border-t border-gray-200 bg-white pb-6 pt-5 sm:py-8">
                <div class="base-container">
                    <h2 class="mb-4 text-lg font-bold text-gray-900">{{ \App\Support\ProductPageMeta::similarHeading($product) }}</h2>
                    <div class="grid w-full grid-cols-2 items-stretch gap-1.5 sm:grid-cols-5 sm:gap-2 lg:gap-3">
                        @foreach ($similar as $deal)
                            <x-deal-card :deal="$deal" class="h-full" />
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($hasHistory)
            @php
                $historyPrices = array_values(array_filter(array_map(fn ($h) => (float) ($h['discounted_price'] ?? 0), $history), fn ($p) => $p > 0));
                if ($bestPrice > 0) { $historyPrices[] = $bestPrice; }
            @endphp
            @if ($historyPrices !== [])
                <section id="kainu-istorija" class="scroll-mt-32 border-t border-gray-200 bg-white py-6 sm:py-8">
                    <div class="base-container">
                        <h2 class="mb-4 text-lg font-bold text-gray-900">{{ \App\Support\ProductPageMeta::historyTitle($product['name']) }}</h2>
                        <div class="grid grid-cols-3 gap-3 sm:max-w-md">
                            <div class="rounded-xl border border-gray-100 p-3 text-center">
                                <p class="text-xs font-medium text-gray-500">Mažiausia</p>
                                <p class="mt-1 text-base font-bold text-dark-green">{{ number_format(min($historyPrices), 2, ',', ' ') }} €</p>
                            </div>
                            <div class="rounded-xl border border-gray-100 p-3 text-center">
                                <p class="text-xs font-medium text-gray-500">Vidutinė</p>
                                <p class="mt-1 text-base font-bold text-gray-900">{{ number_format(array_sum($historyPrices) / count($historyPrices), 2, ',', ' ') }} €</p>
                            </div>
                            <div class="rounded-xl border border-gray-100 p-3 text-center">
                                <p class="text-xs font-medium text-gray-500">Didžiausia</p>
                                <p class="mt-1 text-base font-bold text-gray-900">{{ number_format(max($historyPrices), 2, ',', ' ') }} €</p>
                            </div>
                        </div>
                    </div>
                </section>
            @endif
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
