@props(['deal', 'source' => 'grid', 'contextStoreSlug' => null, 'inCarousel' => false])

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
    $infoLabel = trim($deal['info'] ?? '') ?: null;

    $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';

    $hideSingleStoreLogo = $contextStoreSlug
        && $stores->count() === 1
        && ($stores[0]['slug'] ?? null) === $contextStoreSlug;

    $shellClass = 'group relative flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl bg-white p-2.5 transition-opacity hover:opacity-95 sm:p-3';

    if ($inCarousel) {
        // discountCarouselWidthClass in discount-card.tsx — merged onto the card
        // shell so the carousel track doesn't need a separate wrapper div.
        $shellClass .= ' w-[calc((100%-0.75rem)/2.3)] max-w-[164px] min-w-[140px] shrink-0 grow-0 basis-[calc((100%-0.75rem)/2.3)] self-stretch snap-start sm:w-[186px] sm:min-w-[186px] sm:max-w-none sm:basis-auto md:min-w-[214px] lg:min-w-[248px]';
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
            <div class="relative aspect-square w-full overflow-hidden rounded-xl bg-white">
                @if ($product['image_url'])
                    <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy" class="h-full w-full object-contain p-3 sm:p-3.5">
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
                        <span class="text-[22px] font-bold leading-none tabular-nums text-gray-900">{{ $euro($discountPrice) }}</span>
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
                @if ($infoLabel)
                    <p class="line-clamp-2 text-xs leading-snug text-gray-500">{{ $infoLabel }}</p>
                @endif
            </div>

            <p class="{{ ($discountPrice > 0 || $infoLabel) ? 'mt-1.5' : '' }} line-clamp-2 max-h-[2.45rem] min-h-[2.45rem] min-w-0 overflow-hidden text-sm font-normal leading-snug text-gray-900">
                {{ $product['name'] }}
            </p>

            @if ($stores->count() > 1)
                <div class="mt-auto flex min-w-0 items-center gap-1.5 pt-2">
                    @foreach ($stores->take(3) as $storeItem)
                        <x-store-logo :slug="$storeItem['slug']" :name="$storeItem['name']" size="xs" />
                    @endforeach
                    @if ($stores->count() > 3)
                        <span class="text-xs font-medium text-gray-400">+{{ $stores->count() - 3 }}</span>
                    @endif
                </div>
            @elseif ($stores->count() === 1 && ! $hideSingleStoreLogo)
                <div class="mt-auto flex min-w-0 items-center pt-3">
                    <x-store-logo :slug="$stores[0]['slug']" :name="$stores[0]['name']" size="xs" class="object-left" />
                </div>
            @endif
        </div>
    </a>

    <div class="absolute right-2 top-2 z-20">
        <x-favorite-button :product-id="$product['id']" :favorited="\App\Support\FavoritedProducts::has($product['id'])" />
    </div>
</div>
