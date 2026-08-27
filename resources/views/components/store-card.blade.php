@props(['store', 'layout' => 'slider'])

@php
    use App\Support\LithuanianPlural;

    $hasOffers = ($store['discounts_count'] ?? 0) > 0;
    $widthClass = $layout === 'slider'
        ? 'w-[calc((100%-0.75rem)/2.2)] min-w-[calc((100%-0.75rem)/2.2)] max-w-[calc((100%-0.75rem)/2.2)] shrink-0 grow-0 basis-[calc((100%-0.75rem)/2.2)] sm:w-[200px] sm:min-w-[200px] sm:max-w-none sm:basis-auto lg:w-[210px]'
        : '';

    // "5 386" -> "5,4k" — only used by the chip layout's corner badge, where
    // full counts don't fit; formatCount()'s thousand-separator form is used
    // everywhere else.
    $abbreviateCount = function (int $n): string {
        return $n >= 1000 ? number_format($n / 1000, 1, ',', '') . 'k' : (string) $n;
    };
@endphp

@if ($layout === 'chip')
    {{-- Quick-access tile, ported from the "Variant A" mobile mockup —
         compact rounded square (logo + corner count badge) with the name
         below, no separate CTA button since the whole tile is clickable.
         Built for the home hero's store row specifically; the taller
         slider/grid cards above are untouched for /parduotuves etc. --}}
    <a href="/akcijos/{{ $store['slug'] }}" class="flex w-[72px] shrink-0 snap-start flex-col items-center gap-1.5 sm:w-20 {{ !$hasOffers ? 'opacity-75' : '' }}">
        <span class="relative flex size-16 shrink-0 items-center justify-center rounded-2xl border border-gray-100 bg-white p-2.5 shadow-sm sm:size-[4.5rem]">
            <img src="/assets/stores/{{ $store['slug'] }}.svg?v=2" alt="" class="max-h-full max-w-full object-contain">
            @if ($hasOffers)
                <span class="absolute -right-1.5 -top-1.5 rounded-full border-2 border-white bg-amber-400 px-1.5 py-0.5 text-[10px] font-extrabold leading-none tabular-nums text-amber-950">
                    {{ $abbreviateCount($store['discounts_count']) }}
                </span>
            @endif
        </span>
        <span class="line-clamp-1 text-center text-xs font-semibold leading-tight text-gray-900">{{ $store['name'] }}</span>
    </a>
@else
    {{-- Ported from discount/src/components/stores/store-card.tsx (slider layout, view-only actions). --}}
    <a href="/akcijos/{{ $store['slug'] }}" class="group flex snap-start flex-col rounded-xl border border-gray-200 bg-white p-3.5 transition-[border-color,box-shadow] hover:border-gray-300 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green/40 sm:rounded-2xl sm:p-4 {{ $widthClass }} {{ !$hasOffers ? 'opacity-75' : '' }}">
        <div class="flex h-[72px] items-center justify-center sm:h-[80px]">
            <x-store-logo :slug="$store['slug']" size="lg" />
        </div>
        <div class="mt-2 flex min-w-0 flex-col gap-0.5 sm:mt-2.5">
            <p class="line-clamp-2 text-center text-sm font-bold leading-snug text-gray-900 sm:text-base">{{ $store['name'] }}</p>
            <p class="truncate text-center text-xs leading-snug text-gray-600 sm:text-sm">
                @if ($hasOffers)
                    {{ LithuanianPlural::formatCount($store['discounts_count']) }} {{ LithuanianPlural::discountWord($store['discounts_count']) }}
                @else
                    Nėra akcijų
                @endif
            </p>
        </div>
        <span class="mt-3 inline-flex h-8 w-full items-center justify-center rounded-lg border border-green bg-white px-2 text-xs font-bold text-green transition-colors group-hover:bg-green/5 group-hover:text-dark-green sm:mt-3.5 sm:h-9 sm:text-sm">
            Žiūrėti akcijas
        </span>
    </a>
@endif
