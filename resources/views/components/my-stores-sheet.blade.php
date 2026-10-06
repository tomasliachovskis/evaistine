@php
    // "Mano parduotuvės" picker. Stores that have offers or leaflets, the
    // five main chains first, then by number of offers. $stores comes from
    // MobileNavComposer (same cached list as the bottom nav's store sheet).
    $mainStores = \App\Support\StoreListPriority::mainSlugs();
    $pickable = collect($stores ?? [])
        ->filter(fn ($s) => ($s['discounts_count'] ?? 0) > 0 || ($s['leaflets_count'] ?? 0) > 0)
        ->sortBy([
            fn ($a, $b) => (array_search($a['slug'], $mainStores, true) === false ? 99 : array_search($a['slug'], $mainStores, true))
                <=> (array_search($b['slug'], $mainStores, true) === false ? 99 : array_search($b['slug'], $mainStores, true)),
            fn ($a, $b) => ($b['discounts_count'] ?? 0) <=> ($a['discounts_count'] ?? 0),
        ])
        ->values();
@endphp

{{-- Opened with $store.myStores.sheetOpen = true (side menu, the listing
     bar's "Keisti", store pages). Edits a copy and saves on "Išsaugoti", so
     closing without saving changes nothing. --}}
<div
    x-data="{ picked: [] }"
    x-init="
        $store.myStores.names = @js($pickable->mapWithKeys(fn ($s) => [$s['slug'] => $s['name']])->all());
        $watch('$store.myStores.sheetOpen', (open) => { if (open) picked = [...$store.myStores.slugs]; });
    "
    x-show="$store.myStores.sheetOpen"
    x-cloak
    @keydown.escape.window="$store.myStores.sheetOpen = false"
    x-back-closes="$store.myStores.sheetOpen"
    class="sheet-backdrop z-[80]"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="my-stores-title"
>
    <div @click.outside="$store.myStores.sheetOpen = false" class="sheet-panel">
        <div class="sheet-handle"></div>
        <div class="sheet-head border-b border-gray-200">
            <h2 id="my-stores-title" class="text-2xl font-bold text-gray-900">Kur jūs perkate?</h2>
            <button type="button" @click="$store.myStores.sheetOpen = false" class="sheet-close" aria-label="Uždaryti">
                <x-app-icon name="x" class="size-7" />
            </button>
        </div>

        <div class="flex flex-col gap-4 px-5 py-5">
            <p class="text-lg leading-snug text-gray-700">Pažymėkite savo parduotuves. Akcijų sąrašuose rodysime tik jų pasiūlymus.</p>

            <div class="flex flex-wrap gap-2.5">
                @foreach ($pickable as $store)
                    <x-store-pick-tile
                        :slug="$store['slug']"
                        :name="$store['name']"
                        checked="picked.includes('{{ $store['slug'] }}')"
                        toggle="picked.includes('{{ $store['slug'] }}') ? picked = picked.filter((s) => s !== '{{ $store['slug'] }}') : picked.push('{{ $store['slug'] }}')"
                    />
                @endforeach
            </div>
        </div>

        <div class="sticky bottom-0 flex flex-col gap-2 border-t border-gray-200 bg-white px-5 py-4 sm:flex-row-reverse">
            <button
                type="button"
                @click="$store.myStores.set(picked); $store.myStores.sheetOpen = false"
                class="flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-action px-5 text-lg font-bold text-white hover:bg-action-hover"
            >
                <x-app-icon name="check" class="size-5" />
                <span x-text="picked.length ? 'Išsaugoti (' + picked.length + ')' : 'Išsaugoti: rodyti visas'"></span>
            </button>
            <button
                type="button"
                x-show="picked.length"
                @click="picked = []"
                class="min-h-12 rounded-xl px-4 text-lg font-semibold text-dark-green underline underline-offset-4 hover:no-underline"
            >
                Nuimti visas žymes
            </button>
        </div>
    </div>
</div>
