@props(['id', 'title', 'subtitle' => null, 'deals', 'icon', 'categorySlug' => null, 'layout' => 'carousel', 'seeAllHref' => null])

@php
    $pageCount = max(1, (int) ceil(count($deals) / 2));
@endphp

{{-- Ported from discount/src/components/landing/landing-deals-section.tsx. --}}
@if (count($deals))
    <section id="{{ $id }}" aria-labelledby="{{ $id }}-heading" class="min-w-0 p-2 max-sm:-mx-4 max-sm:px-4 sm:p-3">
        <div class="mb-2.5 flex items-center justify-between gap-2 sm:mb-3">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    @if ($categorySlug)
                        <img src="/assets/categories/{{ $categorySlug }}.svg" alt="" class="size-4 shrink-0 sm:hidden" onerror="this.style.display='none'">
                    @else
                        <x-app-icon :name="$icon" class="size-4 text-green sm:hidden" />
                    @endif
                    <h2 id="{{ $id }}-heading" class="text-[0.95rem] font-semibold leading-tight text-gray-900 sm:text-2xl sm:font-extrabold sm:leading-snug">
                        {{ $title }}
                    </h2>
                </div>
                @if ($subtitle)
                    <p class="mt-1 hidden text-sm text-gray-600 sm:block">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($seeAllHref)
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ $seeAllHref }}" class="inline-flex items-center gap-0.5 text-sm font-medium text-green hover:text-dark-green sm:font-semibold">
                        Žiūrėti visas
                        <x-app-icon name="chevron-right" class="size-3.5 opacity-80" />
                    </a>
                </div>
            @endif
        </div>

        @if ($layout === 'grid')
            <div class="grid grid-cols-2 items-stretch gap-1.5 sm:grid-cols-5 sm:gap-2 lg:gap-3">
                @foreach (array_slice($deals, 0, 10) as $deal)
                    <x-deal-card :deal="$deal" />
                @endforeach
            </div>
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
                        {{-- discountCarouselWidthClass in discount-card.tsx — needs explicit
                             widths at every breakpoint, not just mobile, or the flex item
                             collapses to content-size and the card's image shrinks to nothing. --}}
                        <div class="h-full w-[calc((100%-0.75rem)/2.3)] max-w-[164px] min-w-[140px] shrink-0 grow-0 basis-[calc((100%-0.75rem)/2.3)] self-stretch snap-start sm:w-[186px] sm:min-w-[186px] sm:max-w-none sm:basis-auto md:min-w-[214px] lg:min-w-[248px]">
                            <x-deal-card :deal="$deal" />
                        </div>
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
