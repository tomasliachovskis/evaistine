@props([
    'store',
    'offer',
    'showBestPriceBadge' => false,
])

@php
    $validityLabel = \App\Support\ProductPageMeta::validUntilLabel($offer['to_date'] ?? null);
    $pct = \App\Support\ProductPageMeta::promotionBadgePercent($offer['discount_percent'] ?? null);
@endphp

<a
    href="/akcijos/{{ $store['slug'] ?? '' }}"
    {{ $attributes->class([
        'relative flex w-full rounded-xl border border-green/35 bg-white p-4 transition-colors hover:border-green/45 sm:p-5',
        'pt-6 sm:pt-7' => $showBestPriceBadge,
        'pr-24 sm:pr-28' => (bool) $validityLabel,
    ]) }}
>
    @if ($showBestPriceBadge)
        <span class="absolute left-3 top-0 z-10 inline-flex -translate-y-1/2 items-center rounded-full bg-green px-3 py-1 text-xs font-bold leading-none text-white sm:left-3.5 sm:px-3.5">
            <x-app-icon name="star" class="mr-1 size-3" fill="currentColor" />
            Geriausia kaina
        </span>
    @endif
    @if ($validityLabel)
        <span class="absolute right-3 top-3 rounded-full bg-[#e8eef3] px-3 py-1 text-xs font-medium text-gray-700 sm:right-4 sm:top-4">{{ $validityLabel }}</span>
    @endif
    <div class="flex min-w-0 items-start gap-3 sm:gap-4">
        <x-store-logo :slug="$store['slug'] ?? ''" :name="$store['name'] ?? ''" size="md" class="shrink-0" />
        <div class="flex min-w-0 flex-1 flex-col gap-1.5 sm:gap-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1">
                @if (!empty($offer['discounted_price']) && $offer['discounted_price'] > 0)
                    <span class="shrink-0 text-xl font-bold tabular-nums text-gray-900 sm:text-2xl">{{ number_format($offer['discounted_price'], 2, ',', ' ') }} €</span>
                @elseif ($pct !== null)
                    <x-discount-badge :percent="$pct" size="sm" />
                @else
                    <span class="text-sm text-gray-500">Kaina nežinoma</span>
                @endif
            </div>
            <div class="inline-flex min-w-0 items-center gap-1.5 text-xs font-normal leading-snug text-gray-500">
                <x-app-icon name="info" class="size-3.5 shrink-0 text-gray-400 sm:size-4" />
                <span class="min-w-0">{{ \App\Support\ProductPageMeta::offerOriginLabel($store['slug'] ?? '', $store['name'] ?? '') }}</span>
            </div>
        </div>
    </div>
</a>
