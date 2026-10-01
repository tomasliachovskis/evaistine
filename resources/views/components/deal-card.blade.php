@props(['deal', 'source' => 'grid', 'contextStoreSlug' => null, 'inCarousel' => false, 'featured' => false, 'stretch' => true, 'compareStores' => false])

@php
    $product = $deal['product'];
    // Every store this product currently has a discount in, primary store
    // (this card's own $deal['store_id']) first, deduped by slug.
    $offerStores = collect($deal['offers'] ?? [])->pluck('store')->filter()->unique('slug')->values();
    $stores = $offerStores->sortBy(fn ($s) => ($s['id'] ?? null) === ($deal['store_id'] ?? null) ? 0 : 1)->values();
    $href = '/akcijos/' . $product['full_slug'];

    $discountPrice = (float) ($deal['discounted_price'] ?? 0);
    $originalPrice = (float) ($deal['original_price'] ?? 0);
    $showOriginal = $originalPrice > 0 && $originalPrice !== $discountPrice && $discountPrice > 0;
    $discountPercent = $deal['discount_percent'] ?? null;
    // Real normalized unit price (discounts.unit_price/unit_price_basis —
    // Rimi/Lidl scrape it directly, others get it parsed from the product
    // name) instead of the raw scraped `info` field, which is free text and
    // just as often something unrelated ("5 rūšys", a condition note) as an
    // actual €/kg figure.
    $unitPriceLabel = \App\Support\UnitPrice::label($deal['unit_price'] ?? null, $deal['unit_price_basis'] ?? null);

    $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';

    // On a store page ($contextStoreSlug set) the store is already
    // established by the page itself — no need to repeat it on every card's
    // logo row, even for a product that's also on sale elsewhere.
    $hideStoreLogos = (bool) $contextStoreSlug;

    // compareStores (leaflet pages): with the logos hidden, say instead
    // whether this store's price is the cheapest or another store sells it
    // for less.
    $priceComparison = $compareStores && $contextStoreSlug
        ? \App\Support\PriceComparison::forDeal($deal, $contextStoreSlug)
        : null;

    // Featured (e.g. price-compare-card's "Gera kaina" match) reuses the
    // site's one existing accent yellow (#ffdb4d, already the discount-%
    // and no-price-fallback badge color) as a border/tint instead of the
    // plain gray-200/gray-50 shell, so the standout card doesn't introduce a
    // second accent color.
    // stretch=false (only passed by the main listing grid, which uses
    // items-start instead of the grid default of stretch — see that grid's
    // own comment) drops h-full: a grid item with an explicit height:100%
    // fills the row's auto-computed height regardless of align-items,
    // which silently defeats items-start and reintroduces the exact
    // stretch-vs-aspect-ratio circular dependency items-start exists to
    // avoid. Every other caller (carousels, homepage) still stretches as
    // before.
    $shellClass = 'group relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border p-2.5 transition-colors sm:p-3 '
        . ($stretch ? 'h-full ' : '')
        . ($featured
            ? 'border border-[#ffdb4d] bg-[#fffbeb] hover:border-[#f0c400]'
            : 'border-gray-200 bg-gray-50 hover:border-gray-300');

    if ($inCarousel) {
        // discountCarouselWidthClass in discount-card.tsx — merged onto the card
        // shell so the carousel track doesn't need a separate wrapper div.
        // Phones: two to a row in a wrapped list (see landing-deals-section),
        // not a sideways strip; the fixed widths only apply from sm up.
        $shellClass .= ' w-[calc(50%-0.25rem)] shrink-0 grow-0 basis-[calc(50%-0.25rem)] self-stretch snap-start sm:w-[186px] sm:min-w-[186px] sm:basis-auto md:min-w-[214px] lg:min-w-[248px]';
    }
@endphp

{{-- Ported from discount/src/components/common/discount-card.tsx's grid
     ("referenceLayout") variant — the only variant used in this migration so
     far (listing grids, carousels, similar-products). A <button> can't nest
     inside this card's <a> (invalid HTML, unreliable click handling), so the
     favorite toggle is a positioned sibling of the link, not a child of it —
     the outer <div> here stands in for React's shared shell className. --}}
<div {{ $attributes->class($shellClass) }}>
    <a
        href="{{ $href }}"
        class="flex h-full min-h-0 flex-1 flex-col"
        data-ga-event="product_card_click"
        data-ga-product-id="{{ $product['id'] }}"
        data-ga-product-name="{{ $product['name'] }}"
        data-ga-source="{{ $source }}"
    >
        <div class="relative shrink-0">
            {{-- Bare photo box (no border/bg) per product decision — the
                 white-bg/border reintroduction tried earlier this session
                 (suspecting it fixed a real-device height-instability bug)
                 turned out unrelated; reverted back to bare. onerror still
                 hides a 404'd <img> instead of the browser's native broken-
                 image icon. --}}
            <div class="relative aspect-square w-full overflow-hidden rounded-lg">
                @if ($product['image_url'])
                    <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy" class="h-full w-full object-contain p-3 sm:p-3.5" onerror="this.style.display='none'">
                @endif
                @if ($discountPrice > 0)
                    <div class="absolute bottom-2 left-2 z-10 sm:bottom-2.5 sm:left-2.5">
                        <x-discount-badge :percent="$discountPercent" size="sm" />
                    </div>
                @endif
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col pt-2.5">
            {{-- Both rows below always reserve their height (min-h, matching
                 discount-card.tsx's inCarousel min-h classes) even when empty —
                 otherwise cards without an original/strikethrough price or an
                 info label end up shorter, and the row's height visibly jumps
                 card to card / page to page. --}}
            <div class="min-h-[1.375rem] sm:min-h-[1.75rem]">
                @if ($discountPrice > 0)
                    <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0">
                        <span class="text-xl font-bold leading-none tabular-nums text-gray-900">{{ $euro($discountPrice) }}</span>
                        @if ($showOriginal)
                            <del class="text-sm font-medium tabular-nums text-gray-400">{{ $euro($originalPrice) }}</del>
                        @endif
                    </div>
                @elseif ($discountPercent)
                    {{-- Ported from the product page hero's same fallback — some
                         flyer-scraped offers (multi-variant packs, "7 rūšių")
                         never get a clean per-item price, only a discount %. --}}
                    <span class="inline-flex items-center rounded-lg bg-[#ffdb4d] px-2 py-1 text-sm font-bold leading-none tabular-nums text-gray-900">
                        Sutaupyk iki {{ (int) round($discountPercent) }}%
                    </span>
                @endif
            </div>

            <div class="mt-1 min-h-[1.125rem] sm:min-h-[1.25rem]">
                @if ($unitPriceLabel)
                    <p class="line-clamp-2 text-xs leading-snug text-gray-500">{{ $unitPriceLabel }}</p>
                @endif
            </div>

            {{-- The whole name, never cut with "…" (older readers couldn't
                 tell "Majonezas KĖDAINIŲ LIETUVIŠKAS…" apart from its
                 siblings). The minimum height keeps short names level; cards
                 in a row stretch to the tallest one, the store logo sits at
                 the bottom (mt-auto). --}}
            <p class="{{ ($discountPrice > 0 || $unitPriceLabel) ? 'mt-1.5' : '' }} min-h-[3lh] min-w-0 break-words text-sm font-normal leading-snug text-gray-900 sm:min-h-[2lh]">
                {{ $product['name'] }}
            </p>

            @if ($priceComparison)
                <p class="mt-auto truncate pt-2 text-xs font-medium {{ $priceComparison['cheapest'] ? 'text-dark-green' : 'text-gray-500' }}">{{ $priceComparison['label'] }}</p>
            @elseif (! $hideStoreLogos && $stores->count() > 1)
                <div class="mt-auto flex min-w-0 items-center gap-1.5 pt-2">
                    @foreach ($stores->take(3) as $storeItem)
                        <x-store-logo :slug="$storeItem['slug']" :name="$storeItem['name']" size="xs" />
                    @endforeach
                    @if ($stores->count() > 3)
                        <span class="text-xs font-medium text-gray-400">+{{ $stores->count() - 3 }}</span>
                    @endif
                </div>
            @elseif (! $hideStoreLogos && $stores->count() === 1)
                <div class="mt-auto flex min-w-0 items-center pt-3">
                    <x-store-logo :slug="$stores[0]['slug']" :name="$stores[0]['name']" size="xs" class="object-left" />
                </div>
            @endif
        </div>
    </a>

    <div class="absolute right-2 top-2 z-20">
        {{-- Decorative badge behind the real button — matches the mockup's
             white circle-with-border look without shrinking the button's
             own 44px tap target (a past mobile-audit fix, kept as-is).
             Explicit inset (not relying on a flex parent) — an absolutely
             positioned child ignores a flex parent's centering and falls
             back to its static position (roughly the box's top-left
             corner), which put the circle out from under the icon. --}}
        <span class="pointer-events-none absolute left-1/2 top-1/2 z-0 size-7 -translate-x-1/2 -translate-y-1/2 rounded-full border border-gray-200 bg-white shadow-sm"></span>
        <span class="relative z-10">
            <x-favorite-button :product-id="$product['id']" :product-name="$product['name']" :product-image="$product['image_url'] ?? null" :favorited="\App\Support\FavoritedProducts::has($product['id'])" />
        </span>
    </div>
</div>
