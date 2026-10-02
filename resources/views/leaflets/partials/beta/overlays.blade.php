{{-- Product card: a real dialog (centered on desktop, full screen on
     phones) instead of a small popover over the flyer. The magnifier at
     the top is the product's own area of the flyer page, enlarged, so the
     small print on the price tag can be read. --}}
<div
    x-show="selected()"
    x-cloak
    @keydown.escape.window="if (selectedId) closeCard()"
    @click.self="closeCard()"
    class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
>
    <template x-if="selected()">
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="leaflet-card-title"
            class="flex max-h-full w-full flex-col overflow-y-auto bg-white p-5 sm:max-w-lg sm:rounded-2xl sm:p-6"
        >
            <div class="mb-3 flex justify-end">
                <button
                    type="button"
                    x-ref="cardClose"
                    @click="closeCard()"
                    class="sheet-close"
                    aria-label="Uždaryti"
                >
                    <x-app-icon name="x" class="size-7" />
                </button>
            </div>

            <div class="mx-auto overflow-hidden rounded-xl border border-gray-200 bg-gray-50" :style="magnifierStyle(selected())" role="img" :aria-label="'Prekė leidinyje: ' + selected().name"></div>

            <h2 id="leaflet-card-title" class="mt-4 text-2xl font-bold leading-snug text-font" x-text="selected().name"></h2>

            <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-price-hero font-extrabold tabular-nums text-font" x-text="euro(selected().price)"></span>
                <span x-show="selected().original > selected().price" class="text-lg text-gray-600">buvo <span class="tabular-nums line-through" x-text="euro(selected().original)"></span></span>
                <span x-show="selected().percent" class="rounded-md bg-[#ffdb4d] px-2 py-0.5 text-lg font-bold text-font" x-text="'-' + selected().percent + ' %'"></span>
            </div>
            <p x-show="selected().unit" class="mt-1 text-lg text-gray-600" x-text="selected().unit"></p>

            <div x-show="selected().comparison" class="mt-5 rounded-xl bg-gray-50 p-4">
                <h3 class="text-lg font-bold text-font">Kitose parduotuvėse</h3>
                <ul class="mt-2 flex flex-col gap-1">
                    <template x-for="other in (selected().comparison?.others || []).slice(0, 5)" :key="other.slug">
                        <li class="flex items-baseline justify-between gap-4 text-lg">
                            <span class="text-font" x-text="other.store"></span>
                            <span class="font-bold tabular-nums text-font" x-text="euro(other.price)"></span>
                        </li>
                    </template>
                </ul>
                <p class="mt-2 text-lg font-bold" :class="selected().comparison?.cheapest ? 'text-dark-green' : 'text-font'" x-text="comparisonSentence(selected())"></p>
            </div>

            <p
                x-show="selected().signal"
                class="mt-3 text-lg"
                :class="selected().signal?.tone === 'good' ? 'font-semibold text-dark-green' : 'text-font'"
                x-text="selected().signal ? selected().signal.label + ' ' + selected().signal.description : ''"
            ></p>

            <button
                type="button"
                @click="toggleList(selected())"
                class="mt-5 inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-xl text-lg font-bold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/40"
                :class="inList(selected().id) ? 'bg-green-soft text-dark-green' : 'bg-action text-white hover:bg-action-hover'"
            >
                <x-app-icon x-show="!inList(selected().id)" name="plus" class="size-6" />
                <x-app-icon x-show="inList(selected().id)" name="check" class="size-6" />
                <span x-text="inList(selected().id) ? 'Prekė sąraše (paspauskite, kad išimtumėte)' : 'Įdėti į pirkinių sąrašą'"></span>
            </button>

            <div class="mt-3 flex flex-wrap gap-3">
                <template x-for="h in [selected()]" :key="h.id">
                    <button
                        type="button"
                        x-data="favoriteButton(h.product_id, false, h.name, h.image)"
                        @click.stop.prevent="toggle()"
                        class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 text-lg font-semibold text-font hover:border-gray-300 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
                    >
                        <x-app-icon name="heart" class="size-5" x-bind:class="favorited ? 'fill-red-500 text-red-500' : 'fill-none'" />
                        <span x-text="favorited ? 'Kaina sekama' : 'Sekti kainą'"></span>
                    </button>
                </template>
                <a
                    :href="selected().href"
                    class="inline-flex min-h-12 flex-1 items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-4 text-lg font-semibold text-font hover:border-gray-300 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
                >
                    Visa informacija
                    <x-app-icon name="chevron-right" class="size-5" />
                </a>
            </div>
        </div>
    </template>
</div>

{{-- Confirmation of every list change, with undo. --}}
<div
    x-show="toast"
    x-cloak
    x-transition.opacity
    role="status"
    class="fixed inset-x-3 bottom-24 z-[70] mx-auto flex max-w-md items-center justify-between gap-3 rounded-xl bg-font px-4 py-3 text-lg text-white shadow-lg lg:bottom-8"
>
    <span x-text="toast?.message"></span>
    <button type="button" x-show="toast?.undoable" @click="undo()" class="min-h-12 shrink-0 rounded-lg px-3 font-bold text-[#ffdb4d] underline underline-offset-4 hover:no-underline">Atšaukti</button>
</div>

{{-- Shopping list: side panel on desktop, full screen on phones. --}}
<div
    x-show="listOpen"
    x-cloak
    @click.self="closeList()"
    @keydown.escape.window="if (listOpen) closeList()"
    class="fixed inset-0 z-[60] flex justify-end bg-black/50"
>
    <div role="dialog" aria-modal="true" aria-labelledby="shopping-list-title" class="flex h-full w-full max-w-lg flex-col bg-white">
        <div class="flex items-start justify-between gap-3 border-b-2 border-gray-200 px-5 py-4">
            <div>
                <h2 id="shopping-list-title" class="text-2xl font-bold text-font">Pirkinių sąrašas</h2>
                <p class="mt-1 text-base text-gray-600">Sąrašas saugomas šiame įrenginyje.</p>
            </div>
            <button type="button" @click="closeList()" class="sheet-close" aria-label="Uždaryti">
                <x-app-icon name="x" class="size-7" />
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <p x-show="list.length === 0" class="text-lg text-font">Sąrašas tuščias. Paspauskite ant prekės leidinyje ir pasirinkite „Įdėti į pirkinių sąrašą“.</p>
            <template x-for="group in listByStore()" :key="group.store">
                <section class="mb-6">
                    <h3 class="mb-2 text-xl font-bold text-font" x-text="group.store"></h3>
                    <template x-for="item in group.items" :key="item.id">
                        <div class="flex items-start gap-3 border-b border-gray-200 py-3">
                            <button
                                type="button"
                                role="checkbox"
                                :aria-checked="item.checked"
                                @click="toggleChecked(item.id)"
                                class="flex size-12 shrink-0 items-center justify-center rounded-md border"
                                :class="item.checked ? 'border-action bg-action text-white' : 'border-gray-500 bg-white'"
                                :aria-label="(item.checked ? 'Nupirkta: ' : 'Nenupirkta: ') + item.name"
                            ><x-app-icon x-show="item.checked" name="check" class="size-5" /></button>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg leading-snug text-font" :class="item.checked && 'text-gray-500 line-through'" x-text="item.name"></p>
                                <p class="mt-0.5 text-base text-gray-600">
                                    <a :href="item.flyer_href" class="underline underline-offset-2" x-text="item.page + ' puslapis'"></a>
                                    <span x-show="item.cheaper" x-text="' · ' + item.cheaper"></span>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="text-lg font-bold tabular-nums text-font" x-text="euro(item.price)"></span>
                                <button type="button" @click="removeFromList(item.id)" class="min-h-12 text-base font-semibold text-dark-green underline underline-offset-2 hover:no-underline">Išimti</button>
                            </div>
                        </div>
                    </template>
                </section>
            </template>
        </div>

        <div x-show="list.length > 0" class="border-t-2 border-gray-200 px-5 py-4">
            <div class="mb-4 flex items-baseline justify-between">
                <span class="text-lg text-font">Iš viso (dar nenupirkta)</span>
                <span class="text-2xl font-extrabold tabular-nums text-font" x-text="euro(listTotal())"></span>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button type="button" @click="printList()" class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-action px-4 text-lg font-bold text-white hover:bg-action-hover">
                    <x-app-icon name="printer" class="size-5" />
                    Spausdinti
                </button>
                <button type="button" @click="shareList()" class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 text-lg font-semibold text-font hover:border-gray-300">
                    <x-app-icon name="share-2" class="size-5" />
                    Siųsti į telefoną
                </button>
            </div>
            <button type="button" @click="clearList()" class="mt-3 min-h-12 w-full text-lg font-semibold text-dark-green underline underline-offset-4 hover:no-underline">Išvalyti sąrašą</button>
        </div>
    </div>
</div>
