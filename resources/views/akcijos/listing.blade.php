@php
    $headerType = $listingMeta['type'] ?? null;
    $isRichHeader = in_array($headerType, ['store', 'category', 'store_category'], true);
    $isStoreHeader = $headerType === 'store' || $headerType === 'store_category';

    // The short intro.description is shown up in the new rich header for
    // store/category/store_category pages (see below) — skip it here so it
    // doesn't also render a second time in the bottom SEO block.
    $introDescription = $isRichHeader ? null : ($listingMeta['intro']['description'] ?? null);
    $faqItems = $listingMeta['sections']['faq'] ?? [];
    $isKeyword = $filtersMode === 'keyword';

    // Everything below is keyword-page-only content the backend
    // (KeywordPageService::buildListingMeta()) already computes but the view
    // never rendered — verified against production's real section order:
    // store comparison → related searches → about → tips → FAQ.
    $storeComparisonRows = $isKeyword ? ($listingMeta['sections']['category_stats']['store_comparison'] ?? []) : [];
    $storeComparisonHeading = $isKeyword
        ? ucfirst($listingMeta['keyword_grammar']['genitive'] ?? '') . ' akcijos pagal parduotuves'
        : null;
    $searchExamples = $isKeyword
        ? array_merge($listingMeta['keyword_examples'] ?? [], $listingMeta['keyword_store_keywords'] ?? [])
        : [];
    $keywordCategories = $isKeyword ? ($listingMeta['keyword_categories'] ?? []) : [];
    $tips = $isKeyword ? ($listingMeta['tips'] ?? []) : [];
    // Keyword pages store real admin-authored HTML (intro_html) in
    // intro.seo_about. Store/category/store_category have their own
    // real admin-authored HTML too, just under a different existing key —
    // $seo['seo_description'] (Store::description / Category::description,
    // the same field the old bottom-of-page block already rendered) — reused
    // here rather than generating new prose, so "Apie šias akcijas" is the
    // one real paragraph that already existed, not a duplicate second block.
    $seoAboutHtml = $isKeyword
        ? ($listingMeta['intro']['seo_about'] ?? null)
        : ($isRichHeader ? ($seo['seo_description'] ?? null) : null);
    $primarySearchTerm = $listingMeta['keyword_primary_search_term'] ?? ($listingMeta['keyword_slug'] ?? '');

    // Flat-grid pages only (carousels have no page-by-page pagination) — the
    // "Rodyti daugiau" AJAX button already lets a real user reach every page,
    // but a crawler following only real hrefs previously had no way past
    // page 1 except via the product sitemap. rel=next/prev plus the
    // crawlable <a> next to the button (see discount-filters.blade.php) make
    // deep pages reachable through on-page link-following too.
    $paginationCurrent = (int) ($pagination['current_page'] ?? 1);
    $paginationLast = (int) ($pagination['last_page'] ?? 1);
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? $pageTitle"
    :description="$seo['meta_description'] ?? null"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($faqSchema)
            <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @if ($itemListSchema)
            <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @if (empty($sections))
            @if ($paginationCurrent > 1)
                <link rel="prev" href="{{ \App\Support\CanonicalUrl::build($basePath, ['page' => $paginationCurrent - 1]) }}">
            @endif
            @if ($paginationCurrent < $paginationLast)
                <link rel="next" href="{{ \App\Support\CanonicalUrl::build($basePath, ['page' => $paginationCurrent + 1]) }}">
            @endif
        @endif
    @endpush

    {{-- Ported from discount/src/components/common/discounts-layout.tsx (exact
         classes: breadcrumb row, h1 + total-count, sidebar/content two-column
         layout). The filter panel itself lives inside the Livewire component
         below; this file only owns the page chrome around it. --}}
    <div class="base-container pb-1.5 pt-3">
        <nav class="flex flex-wrap items-center gap-1" aria-label="Naršymo kelias">
            @foreach ($breadcrumbs as $index => $crumb)
                @if ($index > 0)<x-app-icon name="arrow-right" class="size-3.5 text-gray-300" />@endif
                @php $isLast = $index === count($breadcrumbs) - 1; @endphp
                <a href="{{ $crumb['slug'] === '/' ? '/' : '/' . ltrim($crumb['slug'], '/') }}" class="text-sm transition-colors hover:text-green {{ $isLast ? 'font-medium text-green' : 'text-gray-600' }}">{{ $crumb['name'] }}</a>
            @endforeach
        </nav>
    </div>

    <div class="base-container gap-4 pb-4 pt-2 sm:pb-5 sm:pt-3">
        @php $total = $pagination['total'] ?? count($deals); @endphp

        @if ($isRichHeader)
            <div class="mb-2 flex flex-col gap-4 sm:mb-4">
                <x-type-hero
                    :eyebrow="$headerType === 'store' ? 'Parduotuvė' : ($headerType === 'category' ? 'Kategorija' : 'Parduotuvė · Kategorija')"
                    :icon-src="$isStoreHeader ? '/assets/stores/' . $listingMeta['store_slug'] . '.svg' : '/assets/categories/' . $listingMeta['category_slug'] . '.svg'"
                    :title="$pageTitle"
                    :subtitle="$listingMeta['intro']['description'] ?? null"
                >
                    @if ($headerType === 'store')
                        @if ($total > 0)
                            <span><b class="font-extrabold text-gray-900">{{ number_format($total, 0, ',', ' ') }}</b> akcijos</span>
                        @endif
                        @if (($listingMeta['locations_count'] ?? 0) > 0)
                            <a href="/parduotuves/{{ $listingMeta['store_slug'] }}" class="inline-flex items-center gap-0.5 font-semibold text-green hover:text-dark-green">
                                {{ number_format($listingMeta['locations_count'], 0, ',', ' ') }} parduotuvės Lietuvoje
                                <x-app-icon name="arrow-right" class="size-3 shrink-0" />
                            </a>
                        @endif
                    @elseif ($headerType === 'store_category')
                        @if ($total > 0)
                            <span><b class="font-extrabold text-gray-900">{{ number_format($total, 0, ',', ' ') }}</b> akcijos</span>
                        @endif
                    @else
                        @foreach ($listingMeta['intro']['quick_stats'] ?? [] as $stat)
                            <span><b class="font-extrabold text-gray-900">{{ $stat['value'] }}</b> {{ mb_strtolower($stat['label']) }}</span>
                        @endforeach
                    @endif
                    @if (!empty($listingMeta['intro']['freshness_label']))
                        <x-content-freshness :label="$listingMeta['intro']['freshness_label']" />
                    @endif

                    @if ($isStoreHeader)
                        <x-slot:cta>
                            <x-store-subscribe-button />
                        </x-slot:cta>
                    @endif
                </x-type-hero>

                @if (!empty($listingMeta['intro']['discovery_chips']))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($listingMeta['intro']['discovery_chips'] as $chip)
                            <a href="{{ $chip['href'] }}" class="inline-flex min-h-11 shrink-0 items-center whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-600 transition-colors hover:border-green/40">
                                {{ $chip['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($isStoreHeader)
                    <x-store-nav-tabs
                        :store-slug="$listingMeta['store_slug']"
                        :leaflets-count="$listingMeta['leaflets_count'] ?? 0"
                        :total-offers="$listingMeta['total_offers'] ?? $total"
                        :categories="$listingMeta['sections']['available_categories'] ?? []"
                        :featured-category="$headerType === 'store' ? ($listingMeta['sections']['featured_category'] ?? null) : null"
                        :all-categories-count="count($listingMeta['sections']['available_categories'] ?? [])"
                        :current-category="$headerType === 'store_category' ? ['name' => $listingMeta['category_name'], 'offers_count' => $total] : null"
                        :aria-label="$listingMeta['store_name'] . ' skiltys'"
                        active="akcijos"
                    />
                @else
                    <x-keyword-chips-row :pages="$listingMeta['keyword_pages'] ?? []" />
                @endif
            </div>
        @elseif ($isKeyword)
            {{-- Matches the mockup's keyword-page .type-hero: same shared
                 card, emoji as the icon (real data: KeywordPage::$emoji),
                 eyebrow "Populiari prekė", quick_stats already computed by
                 KeywordPageService::buildQuickStats(). --}}
            <div class="mb-2 flex flex-col gap-4 sm:mb-4">
                <x-type-hero
                    eyebrow="Populiari prekė"
                    :title="$pageTitle"
                    :subtitle="$listingMeta['intro']['short_description'] ?? ($listingMeta['intro']['description'] ?? null)"
                >
                    @foreach ($listingMeta['intro']['quick_stats'] ?? [] as $stat)
                        <span><b class="font-extrabold text-gray-900">{{ $stat['value'] }}</b> {{ mb_strtolower($stat['label']) }}</span>
                    @endforeach
                    @if (!empty($listingMeta['intro']['freshness_label']))
                        <x-content-freshness :label="$listingMeta['intro']['freshness_label']" />
                    @endif

                    <x-slot:icon>
                        <x-app-icon name="flame" class="size-7" />
                    </x-slot:icon>
                </x-type-hero>
            </div>
        @else
            <div class="mb-2 flex flex-col gap-2 sm:mb-4">
                <div class="mb-3 min-w-0">
                    <h1 class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 font-semibold text-gray-900">
                        <span class="text-gray-900">{{ $pageTitle }}</span>
                        @if ($total > 0)
                            <span class="whitespace-nowrap tabular-nums text-gray-500">({{ number_format($total, 0, ',', ' ') }})</span>
                        @endif
                    </h1>
                </div>
            </div>
        @endif

        @if ($headerType === 'category' || $headerType === 'store_category')
            <x-store-category-switch-row
                :title="$listingMeta['switch_row_title'] ?? ''"
                :helper-text="$listingMeta['switch_row_helper'] ?? null"
                :items="$listingMeta['switch_row_items'] ?? []"
            />
        @endif

        @if (!empty($storeComparisonRows))
            {{-- Keyword pages only ($storeComparisonRows is always empty for
                 store/category/store_category) — matches the mockup's order
                 exactly: this is "the one place on the site that shows where
                 it's cheapest right away", so it sits immediately after the
                 hero, before the full offer grid — not buried below it. --}}
            <section class="mt-6 w-full">
                <h2 class="mb-2.5 text-base font-bold leading-tight text-gray-900 sm:mb-3 sm:text-lg">{{ $storeComparisonHeading }}</h2>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
                    @foreach ($storeComparisonRows as $index => $row)
                        @php $isBest = $index === 0 && !empty($row['min_price']); @endphp
                        <a
                            href="{{ $row['cheapest_product_href'] ?? ('/akcijos/paieska/' . rawurlencode($primarySearchTerm) . '?store=' . $row['store_slug']) }}"
                            class="flex flex-col gap-2 rounded-xl border p-2.5 text-left transition-colors sm:p-3 {{ $isBest ? 'border-[#ecd09d] bg-[#fffaeb]' : 'border-gray-200 bg-gray-50 hover:border-green/30' }}"
                        >
                            <div class="flex items-center gap-1.5">
                                <x-store-logo :slug="$row['store_slug']" size="xs" />
                                <span class="truncate text-xs font-bold text-gray-600">{{ $row['store'] }}</span>
                                @if ($isBest)
                                    <span class="ml-auto shrink-0 rounded-md bg-[#ffdb4d] px-1.5 py-0.5 text-[0.65rem] font-extrabold uppercase tracking-wide text-gray-900">Pigiausia</span>
                                @endif
                            </div>

                            <div class="relative aspect-square w-full overflow-hidden rounded-lg border border-gray-200 bg-white">
                                @if (!empty($row['cheapest_product_image']))
                                    <img src="{{ $row['cheapest_product_image'] }}" alt="{{ $row['cheapest_product_name'] }}" loading="lazy" class="h-full w-full object-contain p-2">
                                @endif
                            </div>

                            @if (!empty($row['cheapest_product_name']))
                                <p class="line-clamp-2 min-h-[2.3em] text-xs leading-snug text-gray-600">{{ $row['cheapest_product_name'] }}</p>
                            @endif

                            <div>
                                @if (!empty($row['min_price']))
                                    <span class="text-base font-extrabold tabular-nums text-gray-900 sm:text-lg">{{ number_format($row['min_price'], 2, ',', ' ') }} €</span>
                                @elseif (!empty($row['max_discount_percent']))
                                    {{-- Not run through <x-discount-badge>: that component applies
                                         the >=20% promotion threshold, but this badge always shows
                                         the store's actual max discount regardless of size. --}}
                                    <span class="inline-flex h-[1.6rem] items-center rounded-lg bg-[#ffdb4d] px-2.5 text-sm font-bold leading-none text-gray-900">iki -{{ $row['max_discount_percent'] }}%</span>
                                @else
                                    <span class="text-sm font-semibold text-gray-500">Tik nuolaida</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($isKeyword)
            {{-- Mockup shows this grid under its own heading + total count,
                 not dropped in bare — real genitive grammar already computed
                 for the store-comparison heading above, reused here too. --}}
            <div class="mt-6 flex items-center justify-between gap-2">
                <h2 class="text-base font-bold leading-tight text-gray-900 sm:text-lg">Visos {{ $listingMeta['keyword_grammar']['genitive'] ?? '' }} akcijos</h2>
                @if ($total > 0)
                    <span class="shrink-0 text-sm font-medium text-gray-500">{{ number_format($total, 0, ',', ' ') }} {{ \App\Support\LithuanianPlural::offerWord($total) }}</span>
                @endif
            </div>
        @endif

        @php
            $carouselHtml = ! empty($sections) || ($isStoreHeader && ! empty($topOffers))
                ? view('components.partials.listing-category-carousels', [
                    'sections' => $sections,
                    'primarySlug' => $filtersPrimarySlug,
                    'secondarySlug' => $filtersSecondarySlug,
                    'topOffers' => $isStoreHeader ? $topOffers : [],
                ])->render()
                : '';
        @endphp

        @if ($fallbackOtherStores)
            {{-- This store+category combination has no current offers of its
                 own — rather than an empty grid (or noindex, which would
                 throw away real long-tail SEO value for this exact
                 store+category keyword), show the same category's live
                 offers from other stores so the page still has real,
                 relevant content. --}}
            <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 sm:p-5">
                Šiuo metu {{ $fallbackOtherStores['store_name'] }} neturi aktyvių {{ mb_strtolower($fallbackOtherStores['category_name']) }} akcijų.
                @if (!empty($fallbackOtherStores['data']['data']))
                    Žemiau matote {{ mb_strtolower($fallbackOtherStores['category_name']) }} pasiūlymus kitose parduotuvėse.
                @endif
            </div>

            @if (!empty($fallbackOtherStores['data']['data']))
                <div class="mt-4 grid w-full grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($fallbackOtherStores['data']['data'] as $deal)
                        <x-deal-card :deal="$deal" class="h-full" />
                    @endforeach
                </div>
            @endif
        @else
            <livewire:discount-filters
                :mode="$filtersMode"
                :primary-slug="$filtersPrimarySlug"
                :secondary-slug="$filtersSecondarySlug"
                :initial-deals="$deals"
                :initial-pagination="$pagination"
                :carousel-html="$carouselHtml"
                :show-carousels="! empty($sections) || ($isStoreHeader && ! empty($topOffers))"
                :sidebar-mode="$sidebarMode"
                :primary-store-name="$listingMeta['store_name'] ?? null"
                :key="'filters-'.$basePath"
            />
        @endif

        @if (!empty($searchExamples) || !empty($keywordCategories))
            <section class="mt-10 w-full border-t border-gray-200 pt-6">
                <h2 class="mb-3 text-base font-bold leading-tight text-gray-900 sm:text-lg">Dažniausiai ieškoma</h2>
                <ul class="flex flex-col gap-1.5 text-sm">
                    @foreach ($searchExamples as $term)
                        <li><a href="/akcijos/paieska/{{ rawurlencode($term) }}" rel="nofollow" class="text-gray-700 transition-colors hover:text-green">{{ $term }}</a></li>
                    @endforeach
                    @foreach ($keywordCategories as $category)
                        <li><a href="{{ $category['href'] }}" class="text-gray-700 transition-colors hover:text-green">{{ $category['name'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($seoAboutHtml)
            <div class="category-description mt-10 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <h2>Apie šias akcijas</h2>
                {!! $seoAboutHtml !!}
            </div>
        @endif

        @if (!empty($tips))
            <section class="mt-10 w-full rounded-lg border border-gray-200 bg-white p-4 sm:p-5">
                <h2 class="mb-3 text-base font-bold leading-tight text-gray-900">Patarimai pirkėjams</h2>
                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($tips as $tip)
                        <li class="flex gap-3 rounded-lg border border-gray-100 bg-gray-50/80 p-3.5 sm:p-4">
                            @if (!empty($tip['icon']))
                                <span class="text-xl leading-none" aria-hidden="true">{{ $tip['icon'] }}</span>
                            @endif
                            <div>
                                <p class="font-semibold text-gray-900">{{ $tip['title'] }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ $tip['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (!empty($faqItems))
            <section class="mt-10 w-full border-t border-gray-200 pt-6">
                <h2 class="mb-4 text-lg font-bold text-gray-900">Dažniausiai užduodami klausimai</h2>
                <x-faq-accordion :items="$faqItems" />
            </section>
        @endif

        {{-- SEO description paragraphs — verified against production, these sit
             at the very bottom of the page (above the footer), not under the
             title as earlier built. Keyword pages already show this same text
             via the "Apie šias akcijas" section above (seo_about), so skip it
             here to avoid rendering the same paragraph twice. --}}
        @if ($introDescription && !$isKeyword)
            <div class="mt-10 w-full max-w-2xl border-t border-gray-200 pt-6 text-gray-600">
                <p>{{ $introDescription }}</p>
            </div>
        @elseif (!empty($seo['seo_description']) && !$isKeyword && !$isRichHeader)
            {{-- seo_description carries rich HTML body copy (e.g. Store::description),
                 distinct from the plain-text seo.meta_description used in <meta>.
                 Excluded for keyword pages (KeywordPageDynamicMetaService sets it to
                 the same intro_html already shown in "Apie šias akcijas") AND for
                 store/category/store_category (same reason — $seoAboutHtml above
                 already renders this exact field as "Apie šias akcijas"; rendering
                 it again here would just repeat the same paragraph twice). --}}
            {{-- prose/prose-sm (Tailwind Typography), not a manual text-gray-600 —
                 that greyed out the H2 the raw HTML embeds along with the body
                 copy; prose gives headings and paragraphs distinct colors, matching
                 discounts-layout.tsx's exact class list. --}}
            <div class="category-description mt-10 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">{!! $seo['seo_description'] !!}</div>
        @endif
    </div>
</x-layouts.app>
