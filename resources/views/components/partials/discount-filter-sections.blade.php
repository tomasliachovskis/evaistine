{{-- Persistent category/store list — used inside discount-filters'
     centered modal, once per open facet ($facet: 'categories' | 'stores').
     Explicit props (not ambient $sidebarMode/$primarySlug/$secondarySlug)
     so the same partial renders either facet independently on pages that
     now show both filters at once (category-only, store+category) — an
     $hrefFor closure avoids re-deriving URL shape from ambient variables
     whose meaning differs by page type (a bug found while unifying these:
     $primarySlug means a store on one page type and a category on another).

     Plain navigation links, not wire:click toggles — confirmed against
     production: selecting a row does a full page nav to the dedicated URL
     and highlights only that one row, it doesn't accumulate a multi-select
     query-string filter.

     Optional $stagedModel (an Alpine property name in the enclosing
     x-data scope, e.g. 'stagedStore') switches every row from that
     immediate-nav <a> into a <button> that just marks itself selected in
     that Alpine property instead — used only by the mobile "Filtrai"
     modal's staged store+category selection (discount-filters.blade.php),
     which applies both together on one "Filtruoti" tap. Every other call
     site (desktop store/category modals, site-header's Kategorijos
     modal) omits it and keeps today's exact behavior.

     Optional $gaSource (string) adds GA4 tracking (data-ga-event
     "filter_select", data-ga-item "{store|category}:{slug|'all'}",
     data-ga-source $gaSource) to the immediate-nav <a> branch only —
     never to $stagedModel buttons, since a tap there is just staging, not
     a real selection yet (the mobile modal tracks its own single
     "filter_apply" event instead, once, on "Filtruoti"). Omit $gaSource
     to stay untracked, same as before this was added.

     Standard one-row-per-item list (not a chip-wrap grid) — explicit
     product decision, reversing this session's earlier chip-grid rewrite.
     $rowClass is the same row style closure the sort dropdown in
     discount-filters.blade.php already uses (ported from
     product-filter-controls.tsx's row constants) — reused here instead of
     a third bespoke row style. --}}
@php
    // Cap the always-visible list at 8 rows by default — on a
    // store/category-heavy page this list can run past 20 entries, which on
    // a phone means a lot of scrolling inside the sheet just to find the
    // sort button below it. The active slug (if any) is always pinned into
    // the visible head, even if it would otherwise fall past row 8, so
    // re-opening the sheet never hides the user's own current selection
    // behind "Rodyti daugiau".
    //
    // Optional $visibleLimit override: site-header's Kategorijos modal is a
    // full category directory, not a page-scoped filter — truncating it
    // behind an extra click defeats its own purpose (confirmed live
    // 2026-09-20, right after fixing "Rodyti daugiau" not actually
    // expanding — the request right after was "just show them all here").
    $visibleLimit = $visibleLimit ?? 8;
    $headItems = collect($items)->slice(0, $visibleLimit)->values();
    $tailItems = collect($items)->slice($visibleLimit)->values();
    if ($activeSlug !== null && !$headItems->contains('slug', $activeSlug)) {
        $activeItem = $tailItems->firstWhere('slug', $activeSlug);
        if ($activeItem) {
            $tailItems = $tailItems->reject(fn ($item) => $item['slug'] === $activeSlug)->values();
            $headItems->push($activeItem);
        }
    }

    // Row markup (icon/label/count) is identical either way — only the
    // wrapping tag differs: a real <a href> that navigates immediately, or
    // (when $stagedModel is set) a <button> that just marks itself
    // selected in that Alpine property. $slug is null for the "Visos" row.
    $stagedModel = $stagedModel ?? null;
    $allHref = $allHref ?? null;
    $gaSource = $gaSource ?? null;
    $gaFacet = $facet === 'stores' ? 'store' : 'category';
    $openRow = function (?string $slug) use ($stagedModel, $activeSlug, $rowClass, $hrefFor, $allHref, $gaSource, $gaFacet) {
        $isAllRow = $slug === null;
        if ($stagedModel) {
            $slugJs = \Illuminate\Support\Js::from($slug);

            return '<button type="button" @click="' . $stagedModel . ' = ' . $slugJs . '" :class="' . $stagedModel . ' === ' . $slugJs . ' ? ' . \Illuminate\Support\Js::from($rowClass(true)) . ' : ' . \Illuminate\Support\Js::from($rowClass(false)) . '">';
        }

        $href = $isAllRow ? $allHref : $hrefFor($slug);
        $active = $isAllRow ? $activeSlug === null : $slug === $activeSlug;
        $gaAttrs = $gaSource
            ? ' data-ga-event="filter_select" data-ga-item="' . e($gaFacet . ':' . ($slug ?? 'all')) . '" data-ga-source="' . e($gaSource) . '"'
            : '';

        return '<a href="' . e($href) . '" class="' . e($rowClass($active)) . '"' . $gaAttrs . '>';
    };
    $closeRow = fn () => $stagedModel ? '</button>' : '</a>';
@endphp
@if ($facet === 'categories')
    <section class="flex flex-col gap-0.5" x-data="{ expanded: {{ $tailItems->isEmpty() ? 'true' : 'false' }} }">
        @if ($allHref)
            {!! $openRow(null) !!}
                <x-app-icon name="layout-grid" class="size-5 shrink-0 opacity-70" />
                <span class="min-w-0 flex-1 truncate">Visos kategorijos</span>
            {!! $closeRow() !!}
        @endif
        @foreach ($headItems as $category)
            {!! $openRow($category['slug']) !!}
                <span class="min-w-0 flex-1 truncate text-lg">{{ $category['name'] }}</span>
                @if (isset($category['offers_count']))
                    <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($category['offers_count'], 0, ',', ' ') }}</span>
                @endif
            {!! $closeRow() !!}
        @endforeach
        {{-- x-show + a display:contents wrapper, not <template x-if> — Alpine's
             x-if/x-for templates nested inside this modal's own
             <template x-teleport="body"> (site-header.blade.php) don't
             reliably activate: confirmed live 2026-09-20, "Rodyti daugiau
             (11)" showed the right count but clicking it never actually
             added any DOM nodes, since Alpine's teleport clone doesn't
             re-bind nested templates correctly. x-show only ever toggles
             CSS display on already-real DOM nodes, so it isn't affected by
             that. class="contents" keeps these rows acting as direct flex
             children of the enclosing <section> (its own flex/gap layout
             still applies) instead of one nested block. --}}
        <div x-show="expanded" class="contents">
            @foreach ($tailItems as $category)
                {!! $openRow($category['slug']) !!}
                    <span class="min-w-0 flex-1 truncate text-lg">{{ $category['name'] }}</span>
                    @if (isset($category['offers_count']))
                        <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($category['offers_count'], 0, ',', ' ') }}</span>
                    @endif
                {!! $closeRow() !!}
            @endforeach
        </div>
        @if ($tailItems->isNotEmpty())
            <button type="button" @click="expanded = !expanded" class="flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-base font-semibold text-dark-green text-left transition-colors hover:bg-[#f2f2f2]">
                <x-app-icon name="chevron-down" class="size-5 shrink-0 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                <span x-text="expanded ? 'Rodyti mažiau' : 'Rodyti daugiau ({{ $tailItems->count() }})'"></span>
            </button>
        @endif
    </section>
@else
    <section class="flex flex-col gap-0.5" x-data="{ expanded: {{ $tailItems->isEmpty() ? 'true' : 'false' }} }">
        @if ($allHref)
            {!! $openRow(null) !!}
                <x-app-icon name="store" class="size-5 shrink-0 opacity-70" />
                <span class="min-w-0 flex-1 truncate">Visos vaistinės</span>
            {!! $closeRow() !!}
        @endif
        @foreach ($headItems as $store)
            {!! $openRow($store['slug']) !!}
                <span class="min-w-0 flex-1 truncate text-lg">{{ $store['name'] }}</span>
                @if (isset($store['offers_count']))
                    <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($store['offers_count'], 0, ',', ' ') }}</span>
                @endif
            {!! $closeRow() !!}
        @endforeach
        {{-- x-show + display:contents, not <template x-if> — see the
             categories branch above for why. --}}
        <div x-show="expanded" class="contents">
            @foreach ($tailItems as $store)
                {!! $openRow($store['slug']) !!}
                    <span class="min-w-0 flex-1 truncate text-lg">{{ $store['name'] }}</span>
                    @if (isset($store['offers_count']))
                        <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($store['offers_count'], 0, ',', ' ') }}</span>
                    @endif
                {!! $closeRow() !!}
            @endforeach
        </div>
        @if ($tailItems->isNotEmpty())
            <button type="button" @click="expanded = !expanded" class="flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-base font-semibold text-dark-green text-left transition-colors hover:bg-[#f2f2f2]">
                <x-app-icon name="chevron-down" class="size-5 shrink-0 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                <span x-text="expanded ? 'Rodyti mažiau' : 'Rodyti daugiau ({{ $tailItems->count() }})'"></span>
            </button>
        @endif
    </section>
@endif
