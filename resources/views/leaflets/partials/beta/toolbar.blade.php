{{-- Above the viewer: one standing instruction (not a dismissable tip, so
     it is there on every visit), a search that runs on submit, at most 4
     filters, and the result in words with a button to the next page that
     has results. Nothing here changes the page on its own. --}}
<div class="mb-4 flex flex-col gap-3 text-lg">
    <p class="max-w-3xl leading-relaxed text-font">
        <span class="sm:hidden">Paspauskite ant prekės, kad pamatytumėte kainas ir įsidėtumėte ją į sąrašą.</span>
        <span class="hidden sm:inline">Paspauskite ant prekės leidinyje: pamatysite kainą kitose vaistinėse ir galėsite įsidėti ją į pirkinių sąrašą.</span>
    </p>

    <form @submit.prevent="runSearch()" class="flex flex-wrap items-end gap-2" role="search">
        <label class="flex min-w-0 flex-1 flex-col gap-1 sm:max-w-md">
            <span class="text-base font-semibold text-font">Ieškoti šiame leidinyje</span>
            <input
                type="search"
                x-model="query"
                @search="if (query === '') activeQuery = ''"
                placeholder="pvz. sviestas"
                class="h-12 w-full rounded-lg border border-gray-300 bg-white px-3 text-lg text-font placeholder:text-gray-500 focus:border-dark-green focus:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
            >
        </label>
        <button type="submit" class="inline-flex h-12 items-center gap-2 rounded-lg bg-action px-5 text-lg font-bold text-white hover:bg-action-hover focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/40">
            <x-app-icon name="search" class="size-5" />
            Ieškoti
        </button>
    </form>

    <div x-show="lenses.length > 0" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
        <span class="col-span-2 text-base font-semibold text-font">Rodyti tik:</span>
        <template x-for="lens in lenses" :key="lens.key">
            <button
                type="button"
                @click="setLens(lens.key)"
                :aria-pressed="activeLens === lens.key"
                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border px-4 text-base font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
                :class="activeLens === lens.key ? 'border-action bg-action text-white' : 'border-gray-400 bg-white text-font hover:border-gray-300'"
            >
                <x-app-icon x-show="activeLens === lens.key" name="check" class="size-4" />
                <span x-text="lens.label"></span>
            </button>
        </template>
    </div>

    <div x-show="filtering()" x-cloak class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg bg-green-soft px-4 py-3" aria-live="polite">
        <p class="font-semibold text-dark-green" x-text="resultSentence()"></p>
        <p x-show="matchCount() > 0" class="text-base text-gray-600" x-text="'Šiame puslapyje: ' + spreadMatchCount() + '.'"></p>
        <button
            type="button"
            x-show="nextMatchPage() !== null"
            @click="currentPage = nextMatchPage()"
            class="inline-flex min-h-12 items-center gap-1.5 rounded-lg bg-action px-4 text-base font-bold text-white hover:bg-action-hover focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/40"
        >
            <span x-text="'Rodyti ' + nextMatchPage() + ' puslapį'"></span>
            <x-app-icon name="chevron-right" class="size-5" />
        </button>
        <button type="button" @click="clearFilters()" class="min-h-12 rounded-lg px-3 text-base font-semibold text-dark-green underline underline-offset-4 hover:no-underline">
            Rodyti visas prekes
        </button>
    </div>
</div>
