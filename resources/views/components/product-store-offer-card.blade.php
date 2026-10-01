@props([
    'store',
    'offer',
    'showBestPriceBadge' => false,
    'historical' => false,
    // ['title', 'page', 'href'] when this offer is printed in a current
    // flyer: the card then opens that flyer page and says so in place of
    // the generic origin line.
    'flyerLink' => null,
])

@php
    $validityLabel = \App\Support\ProductPageMeta::validUntilLabel($offer['to_date'] ?? null, $historical);
    $pct = \App\Support\ProductPageMeta::promotionBadgePercent($offer['discount_percent'] ?? null);
    $unitPriceLabel = \App\Support\UnitPrice::label($offer['unit_price'] ?? null, $offer['unit_price_basis'] ?? null);
@endphp

<a
    href="{{ $flyerLink['href'] ?? '/akcijos/' . ($store['slug'] ?? '') }}"
    {{ $attributes->class([
        'relative flex w-full flex-col rounded-xl border border-green/35 bg-white p-4 transition-colors hover:border-green/45 sm:p-5',
        'pt-6 sm:pt-7' => $showBestPriceBadge,
    ]) }}
>
    @if ($showBestPriceBadge)
        <span class="absolute left-3 top-0 z-10 inline-flex -translate-y-1/2 items-center rounded-full bg-action px-3 py-1 text-xs font-bold leading-none text-white sm:left-3.5 sm:px-3.5">
            <x-app-icon name="star" class="mr-1 size-3" fill="currentColor" />
            Geriausia kaina
        </span>
    @endif
    {{-- Two columns: price (+ unit price, shelf-label style) on the left;
         validity chip (and from sm the price origin) on the right. --}}
    <div class="flex w-full min-w-0 items-stretch gap-3 sm:gap-4">
        <x-store-logo :slug="$store['slug'] ?? ''" :name="$store['name'] ?? ''" size="md" class="shrink-0 self-center sm:self-start" />
        <div class="flex min-w-0 flex-1 items-center">
            @if (!empty($offer['discounted_price']) && $offer['discounted_price'] > 0)
                <span class="flex shrink-0 flex-col">
                    <span class="text-xl font-bold tabular-nums text-gray-900 sm:text-2xl">{{ number_format($offer['discounted_price'], 2, ',', ' ') }} €</span>
                    @if ($unitPriceLabel)
                        <span class="text-xs leading-tight tabular-nums text-gray-500">{{ $unitPriceLabel }}</span>
                    @endif
                </span>
            @elseif ($pct !== null)
                <x-discount-badge :percent="$pct" size="sm" />
            @else
                <span class="text-sm text-gray-500">Kaina nežinoma</span>
            @endif
        </div>
        <div class="flex min-w-0 max-w-[50%] shrink flex-col items-end justify-center gap-2 text-right sm:justify-between">
            @if ($validityLabel)
                <span class="whitespace-nowrap rounded-full bg-[#e8eef3] px-3 py-1 text-xs font-medium text-gray-700">{{ $validityLabel }}</span>
            @endif
            <span class="hidden min-w-0 items-start justify-end gap-1.5 text-xs font-normal leading-snug text-gray-500 sm:inline-flex">
                <x-app-icon :name="$flyerLink ? 'bookmark' : 'info'" class="mt-px size-3.5 shrink-0 {{ $flyerLink ? 'text-dark-green' : 'text-gray-400' }} sm:size-4" />
                @if ($flyerLink)
                    <span class="min-w-0 font-medium text-dark-green">Leidinyje „{{ $flyerLink['title'] }}“{{ $flyerLink['page'] ? ", {$flyerLink['page']} psl." : '' }}</span>
                @else
                    <span class="min-w-0">{{ \App\Support\ProductPageMeta::offerOriginLabel($store['slug'] ?? '', $store['name'] ?? '') }}</span>
                @endif
            </span>
        </div>
    </div>
    {{-- Phones: the origin gets its own line under the price, so it can't
         run into the unit price beside it. --}}
    <span class="mt-3 flex min-w-0 items-center gap-2 border-t border-gray-100 pt-3 text-sm leading-snug sm:hidden {{ $flyerLink ? 'font-semibold text-dark-green' : 'text-gray-600' }}">
        <x-app-icon :name="$flyerLink ? 'bookmark' : 'info'" class="size-5 shrink-0 {{ $flyerLink ? 'text-dark-green' : 'text-gray-500' }}" />
        @if ($flyerLink)
            <span class="min-w-0 flex-1">Leidinyje „{{ $flyerLink['title'] }}“{{ $flyerLink['page'] ? ", {$flyerLink['page']} psl." : '' }}</span>
            <x-app-icon name="chevron-right" class="size-5 shrink-0" />
        @else
            <span class="min-w-0 flex-1">{{ \App\Support\ProductPageMeta::offerOriginLabel($store['slug'] ?? '', $store['name'] ?? '') }}</span>
        @endif
    </span>
</a>
