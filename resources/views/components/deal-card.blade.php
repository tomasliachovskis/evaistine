@props(['deal'])

@php
    $product = $deal['product'];
    $store = collect($deal['offers'] ?? [])->firstWhere('store_id', $deal['store_id'])['store'] ?? null;
    $href = '/akcijos/' . $product['full_slug'];

    $discountPrice = (float) ($deal['discounted_price'] ?? 0);
    $originalPrice = (float) ($deal['original_price'] ?? 0);
    $showOriginal = $originalPrice > 0 && $originalPrice !== $discountPrice && $discountPrice > 0;
    $infoLabel = trim($deal['info'] ?? '') ?: null;

    // MIN_PROMOTION_BADGE_PERCENT in promotion-percent-badge.tsx.
    $pct = !empty($deal['discount_percent']) && $deal['discount_percent'] >= 20 ? (int) round($deal['discount_percent']) : null;

    $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';
@endphp

{{-- Ported from discount/src/components/common/discount-card.tsx's grid
     ("referenceLayout") variant — the only variant used in this migration so
     far (listing grids, carousels, similar-products). A <button> can't nest
     inside this card's <a> (invalid HTML, unreliable click handling), so the
     favorite toggle is a positioned sibling of the link, not a child of it —
     the outer <div> here stands in for React's shared shell className. --}}
<div class="group relative flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl bg-white p-2.5 transition-opacity hover:opacity-95 sm:p-3">
    <a href="{{ $href }}" class="flex h-full min-h-0 flex-1 flex-col">
        <div class="relative shrink-0">
            <div class="relative aspect-square w-full overflow-hidden rounded-xl bg-white">
                @if ($product['image_url'])
                    <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy" class="h-full w-full object-contain p-3 sm:p-3.5">
                @endif
                @if ($pct !== null)
                    <div class="absolute bottom-2 left-2 z-10 sm:bottom-2.5 sm:left-2.5">
                        <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#ffdb4d] px-2 py-1 text-[13px] font-bold leading-none text-gray-900 tabular-nums">
                            -{{ $pct }}%
                        </span>
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

            @if ($store)
                <div class="mt-auto flex min-w-0 items-center pt-3">
                    <img src="/assets/stores/{{ $store['slug'] }}.svg" alt="{{ $store['name'] }}" class="h-6 w-auto max-w-[4.5rem] shrink-0 object-contain object-left sm:h-7 sm:max-w-[5rem]">
                </div>
            @endif
        </div>
    </a>

    <div class="absolute right-2 top-2 z-20">
        <x-favorite-button :product-id="$product['id']" :favorited="\App\Support\FavoritedProducts::has($product['id'])" />
    </div>
</div>
