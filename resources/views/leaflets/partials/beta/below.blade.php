{{-- In the leaflet page's side column: the products of the current page as
     readable rows, each with its own add-to-list button. --}}
<section x-show="spreadHotspots().length > 0" x-cloak aria-labelledby="spread-products-heading">
    <h2 id="spread-products-heading" class="mb-3 text-xl font-bold text-font">
        Šio puslapio prekės
        <span class="font-normal text-gray-600" x-text="'(' + spreadHotspots().length + ')'"></span>
    </h2>
    <div class="flex flex-wrap gap-3">
        <template x-for="h in spreadHotspots()" :key="h.id">
            <div
                class="flex w-full items-center gap-3 rounded-xl border bg-white p-3"
                :class="filtering() && !isMatch(h) ? 'border-gray-200 opacity-50' : (hoveredId === h.id ? 'border-green' : 'border-gray-300')"
                @mouseenter="hoveredId = h.id"
                @mouseleave="hoveredId = null"
            >
                <button type="button" @click="openCard(h)" class="flex min-w-0 flex-1 items-center gap-3 text-left focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30">
                    <img x-show="h.image" :src="h.image" alt="" loading="lazy" class="size-16 shrink-0 rounded-md bg-gray-50 object-contain">
                    <span class="min-w-0">
                        <span class="line-clamp-3 text-lg leading-snug text-font" x-text="h.name"></span>
                        <span class="mt-0.5 block text-xl font-bold tabular-nums text-font" x-text="euro(h.price)"></span>
                    </span>
                </button>
                <button
                    type="button"
                    @click="toggleList(h)"
                    class="inline-flex min-h-12 shrink-0 items-center gap-1.5 rounded-lg px-3 text-base font-bold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/40"
                    :class="inList(h.id) ? 'bg-green-soft text-dark-green' : 'bg-action text-white hover:bg-action-hover'"
                    :aria-label="(inList(h.id) ? 'Išimti iš sąrašo: ' : 'Įdėti į sąrašą: ') + h.name"
                >
                    <x-app-icon x-show="!inList(h.id)" name="plus" class="size-5" />
                    <x-app-icon x-show="inList(h.id)" name="check" class="size-5" />
                    <span x-text="inList(h.id) ? 'Sąraše' : 'Į sąrašą'"></span>
                </button>
            </div>
        </template>
    </div>
</section>
