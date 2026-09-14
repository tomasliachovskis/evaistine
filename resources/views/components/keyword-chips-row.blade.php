@props(['pages', 'ariaLabel' => 'Susiję pasiūlymai'])

{{-- Related keyword-page links (e.g. "pieno gaminiai", "šokoladas") as a
     wrapping pill row — never horizontal-scroll: this list grows with however
     many related pages a category has, and hiding items behind a swipe a
     40+ user might not discover is exactly the pattern ruled out everywhere
     else on these pages (nav-tabs-row, chip-row, switch-row all wrap too).
     Collapsed to 2 rows by default instead (some of these lists run past
     30 chips — /leidiniai's store row, a category's subcategory row — an
     unbounded wrap block that long pushes the actual page content far down),
     with a "Rodyti daugiau" toggle to reveal the rest. Row height is measured
     at runtime rather than hardcoded, since chip height/wrapping can vary by
     content and breakpoint. --}}
@if (!empty($pages))
    <div
        x-data="{
            expanded: false,
            overflowing: false,
            collapsedHeight: 0,
            init() {
                this.$nextTick(() => {
                    const first = this.$refs.chipList.children[0];
                    if (!first) { return; }
                    const rowHeight = first.offsetHeight;
                    const gap = parseFloat(getComputedStyle(this.$refs.chipList).rowGap) || 0;
                    this.collapsedHeight = Math.round(rowHeight * 2 + gap);
                    this.overflowing = this.$refs.chipList.scrollHeight > this.collapsedHeight + 4;
                });
            },
        }"
    >
        <nav
            x-ref="chipList"
            aria-label="{{ $ariaLabel }}"
            class="flex flex-wrap items-center gap-2.5 overflow-hidden transition-[max-height] duration-300"
            :style="!expanded && collapsedHeight ? ('max-height:' + collapsedHeight + 'px') : ''"
        >
            @foreach ($pages as $page)
                <a href="{{ $page['href'] }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-600 transition-colors hover:border-green/40">
                    @if (!empty($page['logo_slug']))
                        <x-store-logo :slug="$page['logo_slug']" :name="$page['title']" size="xs" />
                    @endif
                    {{ $page['title'] }}
                    @if (!empty($page['matching_offers_count']))
                        <x-count-pill :count="$page['matching_offers_count']" color="gray" />
                    @endif
                </a>
            @endforeach
        </nav>

        <button
            type="button"
            x-show="overflowing"
            x-cloak
            @click="expanded = !expanded"
            class="mt-2 inline-flex items-center gap-1 text-sm font-bold text-green hover:text-dark-green"
        >
            <span x-text="expanded ? 'Rodyti mažiau' : 'Rodyti daugiau'"></span>
            <x-app-icon name="chevron-down" class="size-4 transition-transform" x-bind:class="expanded && 'rotate-180'" />
        </button>
    </div>
@endif
