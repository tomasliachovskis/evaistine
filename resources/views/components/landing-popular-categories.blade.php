@props(['categories', 'title' => 'Populiarios kategorijos', 'linkHref' => '/akcijos'])

{{-- Ported from discount/src/components/landing/landing-popular-categories.tsx (carousel layout, as used on the home page). --}}
@if (count($categories))
    <section aria-labelledby="landing-categories-heading" class="min-w-0">
        <div class="mb-4 flex items-start gap-3">
            <h2 id="landing-categories-heading" class="min-w-0 flex-1 text-lg font-extrabold leading-snug text-gray-900 sm:text-xl">
                {{ $title }}
            </h2>
            <a href="{{ $linkHref }}" class="section-link">
                Žiūrėti visas
                <x-app-icon name="chevron-right" class="size-3.5 opacity-80" />
            </a>
        </div>

        <div
            x-data="{
                activePage: 0,
                pageCount: {{ max(1, (int) ceil(count($categories) / 4)) }},
                update() {
                    const el = $refs.track;
                    const maxScroll = el.scrollWidth - el.clientWidth;
                    if (maxScroll <= 0 || this.pageCount <= 1) { this.activePage = 0; return; }
                    const progress = el.scrollLeft / maxScroll;
                    this.activePage = Math.min(this.pageCount - 1, Math.max(0, Math.round(progress * (this.pageCount - 1))));
                },
            }"
            x-init="update()"
            class="relative min-w-0 pr-0 sm:pr-4"
        >
            <div x-ref="track" @scroll.passive="update()" class="scroll-cards-x flex items-stretch gap-2 py-1 sm:gap-3">
                @foreach ($categories as $category)
                    <a href="{{ $category['category_href'] }}" class="group relative flex w-[152px] max-w-[152px] shrink-0 flex-col items-center rounded-xl border border-gray-200 bg-white px-3 py-3 text-center transition-all hover:border-green/40 hover:shadow-sm sm:w-[196px] sm:max-w-[196px] sm:px-4 sm:py-5">
                        <p class="line-clamp-2 w-full text-sm font-semibold leading-snug text-gray-900 group-hover:text-dark-green">
                            {{ $category['category_name'] }}
                        </p>
                        <p class="mt-1.5 text-xs text-gray-500 sm:text-sm">
                            {{ \App\Support\LithuanianPlural::formatCount($category['discounts_count']) }} {{ \App\Support\LithuanianPlural::discountWord($category['discounts_count']) }}
                        </p>
                    </a>
                @endforeach
            </div>
            <template x-if="pageCount > 1">
                <div class="mt-2 flex items-center justify-center gap-1.5 sm:hidden">
                    <template x-for="index in pageCount" :key="index">
                        <span :class="index - 1 === activePage ? 'size-2 rounded-full bg-action' : 'size-1.5 rounded-full bg-gray-300'"></span>
                    </template>
                </div>
            </template>
        </div>
    </section>
@endif
