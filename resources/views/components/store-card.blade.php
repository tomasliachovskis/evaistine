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
            <p class="mb-1 whitespace-nowrap text-center text-xl font-bold leading-none text-gray-900">{{ $store['name'] }}</p>
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
    {{-- Ported from discount/src/components/stores/store-card.tsx. Two
         separate buttons (owner's request, 2026-10): leaflets and offers,
         each only when the store has that page; the logo/name link goes to
         the main one. --}}
    @php
        $showsLeaflets = $leafletsCount > 0 || ! $showsDiscountsPage;
        $buttonClass = 'inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-green-soft px-2 text-base font-bold text-dark-green transition-colors hover:bg-green-soft-border';
    @endphp
    <div class="flex snap-start flex-col rounded-2xl border border-gray-300 bg-white p-3 {{ $widthClass }} {{ !$isActive ? 'opacity-75' : '' }}">
        <a href="{{ $storeHref }}" class="flex flex-col rounded-xl hover:opacity-90">
            <div class="flex h-[72px] items-center justify-center sm:h-[80px]">
                <x-store-logo :slug="$store['slug']" size="lg" />
            </div>
            <p class="mt-2 line-clamp-2 text-center text-xl font-bold leading-tight text-gray-900 sm:mt-2.5">{{ $store['name'] }}</p>
        </a>
        <div class="mt-auto flex flex-col gap-2 pt-3">
            @if ($showsLeaflets)
                <a href="/leidinys/{{ $store['slug'] }}" class="{{ $buttonClass }} gap-2">Leidiniai @if ($leafletsCount > 0)<span class="tabular-nums text-gray-700">{{ $leafletsCount }}</span>@endif</a>
            @endif
            @if ($showsDiscountsPage)
                <a href="/akcijos/{{ $store['slug'] }}" class="{{ $buttonClass }} gap-2">Akcijos <span class="tabular-nums text-gray-700">{{ LithuanianPlural::formatCount($store['discounts_count']) }}</span></a>
            @endif
        </div>
    </div>
@endif
