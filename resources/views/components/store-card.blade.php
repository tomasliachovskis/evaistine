@props(['store', 'layout' => 'slider'])

@php
    use App\Support\LithuanianPlural;

    $hasOffers = ($store['discounts_count'] ?? 0) > 0;
    $leafletsCount = $store['leaflets_count'] ?? 0;
    // Dimmed only when there's truly nothing to see — at least one offer or
    // one leaflet makes the store active (leaflet-only stores like Avon,
    // Apotheka read as inactive otherwise).
    $isActive = $hasOffers || $leafletsCount > 0;
    // Links to the offers page only when there are offers to see there —
    // leaflet-only stores (their /akcijos URL 301s to the leaflet hub) and
    // stores with 0 offers right now link their leaflet hub instead.
    // Missing key (older cached payload) keeps the offers link.
    $showsDiscountsPage = ($store['shows_discounts_page'] ?? true) && $hasOffers;
    $storeHref = $showsDiscountsPage ? "/akcijos/{$store['slug']}" : "/leidinys/{$store['slug']}";
    $widthClass = $layout === 'slider'
        ? 'w-[calc((100%-0.75rem)/2.2)] min-w-[calc((100%-0.75rem)/2.2)] max-w-[calc((100%-0.75rem)/2.2)] shrink-0 grow-0 basis-[calc((100%-0.75rem)/2.2)] sm:w-[200px] sm:min-w-[200px] sm:max-w-none sm:basis-auto lg:w-[210px]'
        : '';
@endphp

@if ($layout === 'chip')
    {{-- Quick-access tile for the home hero's store row — same bordered
         card look as the /parduotuves grid card below (logo, bold name,
         count as plain text), just narrower and without the "Žiūrėti
         akcijas" CTA since the whole tile is already clickable. --}}
    <a href="{{ $storeHref }}" class="flex w-28 shrink-0 snap-start flex-col rounded-xl border border-gray-200 bg-white p-2.5 transition-[border-color,box-shadow] hover:border-gray-300 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green/40 sm:w-32 sm:p-3 {{ !$isActive ? 'opacity-75' : '' }}">
        <div class="flex h-14 items-center justify-center sm:h-16">
            <x-store-logo :slug="$store['slug']" :name="$store['name']" size="md" />
        </div>
        <div class="mt-2 flex min-w-0 flex-col gap-0.5 sm:mt-2.5">
            <p class="mb-1 whitespace-nowrap text-center text-[22px] font-bold leading-none text-gray-900">{{ $store['name'] }}</p>
            {{-- No offers: no "Nėra akcijų" line, the leaflet count below is all it shows. --}}
            @if ($hasOffers)
                <p class="truncate text-center text-sm leading-snug text-gray-600">
                    {{ LithuanianPlural::formatCount($store['discounts_count']) }} {{ LithuanianPlural::discountWord($store['discounts_count']) }}
                </p>
            @endif
            @if ($leafletsCount > 0)
                <p class="truncate text-center text-sm leading-snug text-gray-500">
                    {{ $leafletsCount }} {{ LithuanianPlural::leafletWord($leafletsCount) }}
                </p>
            @endif
        </div>
    </a>
@else
    {{-- Ported from discount/src/components/stores/store-card.tsx (slider layout, view-only actions). --}}
    <a href="{{ $storeHref }}" class="group flex snap-start flex-col rounded-xl border border-gray-200 bg-white p-2.5 transition-[border-color,box-shadow] hover:border-gray-300 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green/40 sm:p-3 {{ $widthClass }} {{ !$isActive ? 'opacity-75' : '' }}">
        <div class="flex h-[72px] items-center justify-center sm:h-[80px]">
            <x-store-logo :slug="$store['slug']" size="lg" />
        </div>
        <div class="mt-2 flex min-w-0 flex-col gap-0.5 sm:mt-2.5">
            <p class="mb-1 line-clamp-2 text-center text-[22px] font-bold leading-none text-gray-900">{{ $store['name'] }}</p>
            {{-- No offers: no "Nėra akcijų" line, the leaflet count below is all it shows. --}}
            @if ($hasOffers)
                <p class="truncate text-center text-sm leading-snug text-gray-600">
                    {{ LithuanianPlural::formatCount($store['discounts_count']) }} {{ LithuanianPlural::discountWord($store['discounts_count']) }}
                </p>
            @endif
            @if ($leafletsCount > 0)
                <p class="truncate text-center text-sm leading-snug text-gray-500">
                    {{ $leafletsCount }} {{ LithuanianPlural::leafletWord($leafletsCount) }}
                </p>
            @endif
        </div>
        <span class="mt-3 inline-flex h-8 w-full items-center justify-center rounded-lg border border-green bg-white px-2 text-sm font-bold text-green transition-colors group-hover:bg-green/5 group-hover:text-dark-green sm:mt-3.5 sm:h-9">
            {{ $showsDiscountsPage ? 'Žiūrėti akcijas' : 'Žiūrėti leidinius' }}
        </span>
    </a>
@endif
