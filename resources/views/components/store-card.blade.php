@props(['store', 'layout' => 'slider'])

@php
    use App\Support\LithuanianPlural;

    $hasOffers = ($store['discounts_count'] ?? 0) > 0;
    $widthClass = $layout === 'slider'
        ? 'w-[calc((100%-0.75rem)/2.2)] min-w-[calc((100%-0.75rem)/2.2)] max-w-[calc((100%-0.75rem)/2.2)] shrink-0 grow-0 basis-[calc((100%-0.75rem)/2.2)] sm:w-[200px] sm:min-w-[200px] sm:max-w-none sm:basis-auto lg:w-[210px]'
        : '';
@endphp

{{-- Ported from discount/src/components/stores/store-card.tsx (slider layout, view-only actions). --}}
<a href="/akcijos/{{ $store['slug'] }}" class="group flex snap-start flex-col rounded-xl border border-gray-200 bg-white p-3.5 transition-[border-color,box-shadow] hover:border-gray-300 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green/40 sm:rounded-2xl sm:p-4 {{ $widthClass }} {{ !$hasOffers ? 'opacity-75' : '' }}">
    <div class="flex h-[72px] items-center justify-center sm:h-[80px]">
        <img src="/assets/stores/{{ $store['slug'] }}.svg" alt="" class="max-h-[56px] max-w-[120px] object-contain sm:max-h-[64px] sm:max-w-[132px]">
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
    <span class="mt-3 inline-flex h-8 w-full items-center justify-center rounded-lg border border-green bg-white px-2 text-[11px] font-bold text-green transition-colors group-hover:bg-green/5 group-hover:text-dark-green sm:mt-3.5 sm:h-9 sm:text-sm">
        Žiūrėti akcijas
    </span>
</a>
