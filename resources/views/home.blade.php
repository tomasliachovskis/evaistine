<x-layouts.app :title="$title" :description="$description" :canonical="$canonical">
    @push('head')
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="https://superakcijos.lt/assets/logo.svg">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
    @endpush

    {{-- Ported from discount/src/components/landing/landing-home-page.tsx. --}}

    {{-- Hero: LandingHero > LandingHeroStoreSlider --}}
    <div class="border-b border-gray-100 bg-white">
        <div class="base-container mx-auto py-2 sm:py-5 lg:py-5">
            <section class="relative min-w-0">
                <div class="mb-4 min-w-0 sm:mb-0">
                    <div class="mb-4 flex items-start justify-between gap-3 sm:mb-5">
                        <div class="min-w-0">
                            <h1 class="font-extrabold text-gray-900">Daug akcijų ir nuolaidų vienoje vietoje</h1>
                            <p class="mt-1 text-sm leading-snug text-gray-600 sm:mt-1.5 sm:text-base">
                                Raskite naujausias Maxima, Lidl, Rimi, Norfa, Iki ir kitų parduotuvių akcijas vienoje vietoje.
                            </p>
                        </div>
                        <a href="/parduotuves" class="hidden shrink-0 items-center gap-1 rounded-full border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 transition-colors hover:border-gray-400 hover:bg-gray-50 sm:inline-flex sm:px-4 sm:py-2">
                            Žiūrėti visas
                            <x-app-icon name="chevron-right" class="size-4 opacity-70" />
                        </a>
                    </div>

                    <div
                        x-data="{
                            activePage: 0,
                            pageCount: {{ max(1, (int) ceil(count($stores) / 4)) }},
                            update() {
                                const el = $refs.track;
                                const maxScroll = el.scrollWidth - el.clientWidth;
                                if (maxScroll <= 0 || this.pageCount <= 1) { this.activePage = 0; return; }
                                const progress = el.scrollLeft / maxScroll;
                                this.activePage = Math.min(this.pageCount - 1, Math.max(0, Math.round(progress * (this.pageCount - 1))));
                            },
                        }"
                        x-init="update()"
                        class="relative min-w-0"
                    >
                        <div x-ref="track" @scroll.passive="update()" class="scroll-cards-x -mx-1 flex min-w-0 snap-x snap-mandatory items-stretch gap-2 px-1 sm:mx-0 sm:gap-4 sm:px-0">
                            @foreach ($stores as $store)
                                <x-store-card :store="$store" layout="slider" />
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
                </div>
            </section>
        </div>
    </div>

    <div class="bg-background">
        <div class="base-container mx-auto flex flex-col gap-5 pb-6 pt-2 sm:gap-8 sm:pb-10 sm:pt-4">
            {{-- LandingHomeDealsSections: food (grid), non-food (carousel) --}}
            <x-landing-deals-section
                id="food-deals"
                title="Šiandien geriausi pasiūlymai"
                subtitle="Gėrimai, saldumynai, pieno, mėsos, žuvies, vaisių ir daržovių pasiūlymai"
                :deals="array_slice($sections['food_pool'], 0, 10)"
                icon="shopping-basket"
                layout="grid"
            />

            <x-landing-deals-section
                id="non-food-deals"
                title="Namų, grožio ir buities akcijos"
                subtitle="Kosmetika, higiena, buitinė chemija, gyvūnų prekės ir kiti kasdieniai pasiūlymai"
                :deals="$sections['non_food_pool']"
                icon="package"
                layout="carousel"
            />

            {{-- LandingPopularCategories --}}
            <x-landing-popular-categories
                :categories="array_slice($pageMeta['category_highlights'], 0, 8)"
                title="Populiariausios kategorijos"
            />

            {{-- LandingSeoFooter --}}
            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center lg:gap-8">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-gray-900 sm:text-xl">SuperAkcijos.lt – akcijos ir nuolaidos Lietuvoje</h2>
                        <p class="mt-3 text-sm leading-relaxed text-gray-600 sm:text-base">
                            {{ $pageMeta['seo']['intro_lead'] }} {{ $pageMeta['seo']['intro_support'] }}
                        </p>
                    </div>
                    <div class="flex size-28 shrink-0 items-center justify-center self-center rounded-2xl bg-gradient-to-br from-green to-dark-green text-white shadow-md sm:size-32" aria-hidden="true">
                        <span class="text-5xl font-black sm:text-6xl">%</span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
