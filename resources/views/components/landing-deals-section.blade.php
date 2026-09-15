@props(['id', 'title', 'subtitle' => null, 'deals', 'icon', 'categorySlug' => null, 'layout' => 'carousel', 'seeAllHref' => null, 'seeAllCount' => null, 'contextStoreSlug' => null])

@php
    $pageCount = max(1, (int) ceil(count($deals) / 2));
@endphp

{{-- Ported from discount/src/components/landing/landing-deals-section.tsx. --}}
@if (count($deals))
    <section id="{{ $id }}" aria-labelledby="{{ $id }}-heading" class="min-w-0">
        <div class="mb-2.5 flex items-center justify-between gap-2 sm:mb-3">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <h2 id="{{ $id }}-heading" class="section-heading-lg">
                        {{ $title }}
                    </h2>
                </div>
                @if ($subtitle)
                    <p class="mt-1 hidden text-sm text-gray-600 sm:block">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($seeAllHref)
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ $seeAllHref }}" class="inline-flex items-center gap-0.5 whitespace-nowrap text-sm font-medium text-green hover:text-dark-green sm:font-semibold">
                        Žiūrėti visas{{ $seeAllCount ? ' ('.number_format($seeAllCount, 0, ',', ' ').')' : '' }}
                        <x-app-icon name="chevron-right" class="size-3.5 opacity-80" />
                    </a>
                </div>
            @endif
        </div>

        @if ($layout === 'grid')
            <div class="grid grid-cols-2 items-stretch gap-1.5 sm:grid-cols-5 sm:gap-2 lg:gap-3">
                @foreach (array_slice($deals, 0, 10) as $deal)
                    <div class="{{ $loop->index >= 4 ? 'hidden sm:block' : '' }}">
                        <x-deal-card :deal="$deal" :context-store-slug="$contextStoreSlug" />
                    </div>
                @endforeach
            </div>
            <a href="/akcijos" data-ga-event="load_more_click" data-ga-source="home_landing" class="mt-3 flex h-10 w-full items-center justify-center rounded-lg border border-green bg-white text-sm font-bold text-green transition-colors hover:bg-green/5 hover:text-dark-green sm:hidden">
                Rodyti daugiau
            </a>
        @else
            <div
                x-data="{
                    activePage: 0,
                    pageCount: {{ $pageCount }},
                    update() {
                        const el = $refs.track;
                        const maxScroll = el.scrollWidth - el.clientWidth;
                        if (maxScroll <= 0 || this.pageCount <= 1) { this.activePage = 0; return; }
                        const progress = el.scrollLeft / maxScroll;
                        this.activePage = Math.min(this.pageCount - 1, Math.max(0, Math.round(progress * (this.pageCount - 1))));
                    },
                }"
                x-init="update()"
            >
                <div x-ref="track" @scroll.passive="update()" class="scroll-cards-x -mx-0.5 flex w-full min-w-0 snap-x snap-mandatory items-stretch gap-1.5 px-0.5 sm:mx-0 sm:gap-3 sm:px-0">
                    @foreach ($deals as $deal)
                        <x-deal-card :deal="$deal" :context-store-slug="$contextStoreSlug" :in-carousel="true" />
                    @endforeach
                </div>
                <template x-if="pageCount > 1">
                    <div class="mt-2 flex items-center justify-center gap-1.5 sm:hidden">
                        <template x-for="index in pageCount" :key="index">
                            <span :class="index - 1 === activePage ? 'size-2 rounded-full bg-green' : 'size-1.5 rounded-full bg-gray-300'"></span>
                        </template>
                    </div>
                </template>
            </div>
        @endif
    </section>
@endif
