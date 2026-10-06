{{-- Page buttons in the leaflet page's side column, like manoakcijos.lt:
     the first and last page and the ones around the current page, with
     "…" for the gaps (1 … 4 [5] 6 … 12). Built in Alpine from currentPage
     (pagerItems() in leaflets/show.blade.php), since the current page is
     client-side state. With the beta layer, a yellow badge counts the
     search/filter matches on that page. --}}
<nav class="flex flex-wrap items-center gap-1.5" aria-label="Leidinio puslapiai">
    <template x-for="item in pagerItems()" :key="item.key">
        <span class="contents">
            <span x-show="item.page === null" class="flex min-h-12 min-w-8 items-center justify-center text-lg font-bold text-gray-600">…</span>
            <button
                type="button"
                x-show="item.page !== null"
                @click="currentPage = item.page"
                :aria-current="item.page === currentPage ? 'page' : null"
                :aria-label="item.page + ' puslapis'"
                class="relative flex min-h-12 min-w-12 items-center justify-center rounded-lg px-2.5 text-lg font-bold tabular-nums transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
                :class="item.page === currentPage ? 'bg-font text-white' : 'bg-gray-100 text-font hover:bg-gray-200'"
            >
                <span x-text="item.page"></span>
                @if (! empty($beta))
                    <span
                        x-show="filtering() && pageMatchCount(item.page) > 0"
                        x-cloak
                        class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-deal px-1 text-xs font-bold leading-5 text-deal-foreground"
                        x-text="pageMatchCount(item.page)"
                    ></span>
                @endif
            </button>
        </span>
    </template>
</nav>
