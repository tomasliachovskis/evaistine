{{-- Plain @include partial (not a component) — expects $websites (all
     CouponWebsite records with active coupons, already ordered by
     coupons_count desc by the controller).

     Same sticky pill-bar language as leaflets/index.blade.php's store chip
     row (and discount-filters.blade.php / leaflet-quick-links.blade.php) —
     copied class-for-class rather than reused as a shared component, since
     leaflets' own chip bar is built around Store/slug-based <x-store-logo>
     and this one is built around CouponWebsite's own logo_url.

     Split into a scrollable "top 8" segment + a pinned "Visos svetainės"
     button that opens a modal listing every website — a flat scroll row
     with no cap (the original version) stops working once there are 30-50+
     coupon websites: some are buried mid-scroll, and there's no way to
     jump straight to one by name. The modal reuses
     discount-filter-sections.blade.php (the same "top N + full list in a
     modal" component already used by the header's Kategorijos dropdown and
     the akcijos filter sheet) instead of inventing a second list style. --}}
@php
    $topWebsites = $websites->take(8);
    $rowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-base leading-snug text-left transition-colors '
        . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
@endphp

<div
    data-sticky-filter-bar
    x-data="{ allWebsitesOpen: false }"
    @keydown.escape.window="allWebsitesOpen = false"
    class="sticky top-[calc(var(--header-h)+env(safe-area-inset-top,0px))] z-[60] flex items-center gap-2 rounded-2xl border border-gray-300 bg-[#e8e8e8] px-2 py-1 min-h-14 sm:px-[20px]"
>
    <nav aria-label="Svetainės" class="scroll-cards-x flex min-w-0 flex-1 flex-nowrap items-center gap-1">
        @foreach ($topWebsites as $website)
            <a href="/kuponai/{{ $website->slug }}" data-ga-event="filter_select" data-ga-item="coupon_website:{{ $website->slug }}" data-ga-source="kuponai_chip_bar" class="inline-flex min-h-12 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3 text-base font-semibold text-gray-900 hover:bg-[#dedede]">
                @if ($website->logo_url)
                    <img src="{{ $website->logo_url }}" alt="" class="h-6 w-auto max-w-[3rem] object-contain">
                @endif
                <span>{{ $website->name }} ({{ $website->coupons_count }})</span>
            </a>
        @endforeach
    </nav>

    {{-- Pinned outside the scrollable nav (not just the last chip in it) so
         it's always visible without scrolling the row first. Same button
         recipe as discount-filters.blade.php's desktop Vaistinė/
         Kategorija toolbar buttons (icon + label + green count-circle +
         chevron), not the flat chip style above — this is a filter-panel
         trigger, not another website pill, so it should read like the
         site's other filter-toolbar buttons. --}}
    <button
        type="button"
        @click="allWebsitesOpen = true"
        data-ga-event="filter_select"
        data-ga-item="coupon_website:all"
        data-ga-source="kuponai_chip_bar"
        class="inline-flex min-h-12 shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl px-3 text-base font-semibold text-gray-900 hover:bg-[#dedede]"
    >
        <x-app-icon name="layout-grid" class="size-5 shrink-0" />
        <span>Visos svetainės</span>
        <span class="inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-action px-2 text-sm font-bold tabular-nums text-white">{{ $websites->count() }}</span>
        <x-app-icon name="chevron-down" class="size-4 shrink-0 text-gray-500 transition-transform" x-bind:class="allWebsitesOpen ? 'rotate-180' : ''" />
    </button>

    <template x-teleport="body">
        <div x-show="allWebsitesOpen" x-cloak x-back-closes="allWebsitesOpen" class="sheet-backdrop z-[70]" @click.self="allWebsitesOpen = false">
            <div class="sheet-panel px-5 pb-5">
                <div class="sheet-handle"></div>
                <div class="mb-3 mt-3 flex items-center justify-between gap-3 sm:mt-5">
                    <h2 class="text-2xl font-bold text-gray-900">Svetainės</h2>
                    <button type="button" @click="allWebsitesOpen = false" class="sheet-close" aria-label="Uždaryti">
                        <x-app-icon name="x" class="size-7" />
                    </button>
                </div>
                @include('components.partials.discount-filter-sections', [
                    'facet' => 'stores',
                    'items' => $websites->map(fn ($website) => [
                        'slug' => $website->slug,
                        'name' => $website->name,
                        'offers_count' => $website->coupons_count,
                    ])->all(),
                    'activeSlug' => null,
                    'hrefFor' => fn ($slug) => "/kuponai/{$slug}",
                    'rowClass' => $rowClass,
                    'gaSource' => 'kuponai_all_websites_modal',
                    // Full directory, not a page-scoped filter — show every
                    // website immediately, same reasoning as site-header's
                    // Kategorijos modal.
                    'visibleLimit' => $websites->count(),
                ])
            </div>
        </div>
    </template>
</div>
