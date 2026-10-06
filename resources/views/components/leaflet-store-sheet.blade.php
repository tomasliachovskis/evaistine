@props(['open', 'current' => null])

@php
    // Stores with current leaflets, in the same editorial order as
    // /leidiniai (named chains first, then by leaflet count). $stores comes
    // from MobileNavComposer (the cached list the menus use).
    $leafletStores = collect(\App\Support\StoreListPriority::sort(
        collect($stores ?? [])
            ->filter(fn ($s) => ($s['leaflets_count'] ?? 0) > 0)
            ->map(fn ($s) => [...$s, 'discounts_count' => $s['leaflets_count']])
            ->values()
            ->all()
    ));
@endphp

{{-- Store picker on /leidiniai and /leidinys/{store}: the same tiles as
     every other store picker (<x-store-pick-tile>); each opens that store's
     leaflets, the current store is marked. $open is the Alpine variable of
     the page that shows it. --}}
<template x-teleport="body">
    <div x-show="{{ $open }}" x-cloak x-back-closes="{{ $open }}" class="sheet-backdrop z-[70]" @click.self="{{ $open }} = false">
        <div class="sheet-panel px-5 pb-5">
            <div class="sheet-handle"></div>
            <div class="mb-3 mt-3 flex items-center justify-between gap-3 sm:mt-5">
                <h2 class="text-2xl font-bold text-gray-900">Kurios vaistinės leidinius rodyti?</h2>
                <button type="button" @click="{{ $open }} = false" class="sheet-close" aria-label="Uždaryti">
                    <x-app-icon name="x" class="size-7" />
                </button>
            </div>
            <a
                href="/leidiniai"
                class="mb-2.5 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 px-4 text-lg font-bold {{ $current === null ? 'border-action bg-green-soft text-dark-green' : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300' }}"
            >
                <x-app-icon name="layout-grid" class="size-5" />
                Visų vaistinių leidiniai
            </a>
            <div class="flex flex-wrap gap-2.5">
                @foreach ($leafletStores as $leafletStore)
                    <x-store-pick-tile
                        :slug="$leafletStore['slug']"
                        :name="$leafletStore['name']"
                        :href="'/leidinys/' . $leafletStore['slug']"
                        :active="$leafletStore['slug'] === $current"
                        :checkbox="false"
                        :note="$leafletStore['leaflets_count'] . ' ' . \App\Support\LithuanianPlural::leafletWord((int) $leafletStore['leaflets_count'])"
                        data-ga-event="filter_select"
                        :data-ga-item="'store:' . $leafletStore['slug']"
                        data-ga-source="leaflet_store_sheet"
                    />
                @endforeach
            </div>
        </div>
    </div>
</template>
