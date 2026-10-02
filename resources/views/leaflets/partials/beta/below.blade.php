{{-- Under the viewer: big labelled page buttons (the round arrows on the
     image are easy to miss and hard to hit), then the products of the
     current page(s) as readable rows, each with its own add button.
     On phones this bar sits right under the flyer and the two fill the
     screen (sizeViewer() measures it via x-ref="pagerBar"), so it also
     carries the shopping list button that used to float over the flyer. --}}
<div x-ref="pagerBar" class="order-2 mt-2 flex flex-col gap-2 lg:mt-4">
<div class="flex items-center justify-between gap-2">
    <button
        type="button"
        @click="prev()"
        :disabled="!hasPrev()"
        class="inline-flex min-h-12 shrink-0 items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 text-lg font-semibold sm:gap-1.5 sm:px-4 text-font hover:border-gray-300 disabled:invisible focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
    >
        <x-app-icon name="chevron-right" class="size-5 rotate-180" />
        <span class="hidden sm:inline">Ankstesnis puslapis</span><span class="sm:hidden">Atgal</span>
    </button>
    <p class="min-w-0 text-center text-base font-semibold text-font sm:text-lg" x-text="pagesLabel()" aria-live="polite"></p>
    <button
        type="button"
        @click="next()"
        :disabled="!hasNext()"
        class="inline-flex min-h-12 shrink-0 items-center gap-1 rounded-lg bg-action px-3 text-lg font-bold sm:gap-1.5 sm:px-4 text-white hover:bg-action-hover disabled:invisible focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/40"
    >
        <span class="hidden sm:inline">Kitas puslapis</span><span class="sm:hidden">Toliau</span>
        <x-app-icon name="chevron-right" class="size-5" />
    </button>
</div>
<button
    type="button"
    x-show="list.length > 0"
    x-cloak
    @click="openList()"
    class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-green-soft px-4 text-lg font-bold text-dark-green hover:bg-green-soft-border focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30 lg:hidden"
>
    <x-app-icon name="shopping-basket" class="size-6" />
    <span class="whitespace-nowrap" x-text="'Sąrašas: ' + list.length + ' ' + productWord(list.length) + ', ' + euro(listTotal())"></span>
</button>
</div>

<section x-show="spreadHotspots().length > 0" x-cloak class="order-5 mt-6" aria-labelledby="spread-products-heading">
    <h2 id="spread-products-heading" class="mb-3 text-xl font-bold text-font">
        <span x-text="spread().length > 1 ? 'Šių puslapių prekės' : 'Šio puslapio prekės'"></span>
        <span class="font-normal text-gray-600" x-text="'(' + spreadHotspots().length + ')'"></span>
    </h2>
    <div class="flex flex-wrap gap-3">
        <template x-for="h in spreadHotspots()" :key="h.id">
            <div
                class="flex w-full items-center gap-3 rounded-xl border bg-white p-3 lg:w-[calc(50%-0.375rem)]"
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
