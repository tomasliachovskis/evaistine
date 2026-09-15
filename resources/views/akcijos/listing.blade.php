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
    // related searches → about → tips → FAQ.
    // "Dažniausiai ieškoma" (keyword_examples + keyword_store_keywords chips)
    // removed per explicit product decision — every one of those chips
    // linked (rel=nofollow) to /akcijos/paieska/{term}, itself
    // noindex,nofollow (AkcijosController::search()), so the section carried
    // zero link equity and read as a keyword-stuffing wall of chips at the
    // page bottom. keyword_categories was the section's only real link, and
    // it's already the same category the breadcrumb above shows — dropping
    // the whole section duplicates nothing.
    $tips = $isKeyword ? ($listingMeta['tips'] ?? []) : [];
    $relatedPages = $isKeyword ? ($listingMeta['related_pages'] ?? []) : [];
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

    // Flat-grid pages only (carousels have no page-by-page pagination) — the
    // infinite-scroll sentinel already lets a real user reach every page,
    // but a crawler following only real hrefs previously had no way past
    // page 1 except via the product sitemap. rel=next/prev plus the
    // crawlable <a> next to the sentinel (see discount-filters.blade.php) make
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
    <div class="base-container pb-4 pt-3">
        <nav class="flex flex-wrap items-center gap-1" aria-label="Naršymo kelias">
            @foreach ($breadcrumbs as $index => $crumb)
                @if ($index > 0)<x-app-icon name="arrow-right" class="size-3.5 text-gray-300" />@endif
                @php $isLast = $index === count($breadcrumbs) - 1; @endphp
                <a href="{{ $crumb['slug'] === '/' ? '/' : '/' . ltrim($crumb['slug'], '/') }}" class="text-sm transition-colors hover:text-green {{ $isLast ? 'font-medium text-green' : 'text-gray-600' }}">{{ $crumb['name'] }}</a>
            @endforeach
        </nav>
    </div>

    <div class="base-container gap-4 pb-4 sm:pb-5">
        @php $total = $pagination['total'] ?? count($deals); @endphp

        @if ($isRichHeader)
            <div class="mb-2 flex flex-col gap-4 sm:mb-4">
                <x-type-hero
                    :icon-src="$isStoreHeader ? '/assets/stores/' . $listingMeta['store_slug'] . '.svg' : '/assets/categories/' . $listingMeta['category_slug'] . '.svg'"
                    :title="$pageTitle"
                    :subtitle="$listingMeta['intro']['description'] ?? null"
                    :hide-subtitle-on-mobile="true"
                >
                    @if ($headerType === 'store')
                        {{-- No count/freshness line here — the store-nav-tabs
                             row right below already shows the real "Akcijos"
                             count as a pill, so a second "N akcijos" line
                             here was a plain duplicate. --}}
                    @elseif ($headerType === 'store_category')
                        @if ($total > 0)
                            <span>{{ number_format($total, 0, ',', ' ') }} akcijos</span>
                        @endif
                        @if (!empty($listingMeta['intro']['freshness_label']))
                            <x-content-freshness :label="$listingMeta['intro']['freshness_label']" />
                        @endif
                    @else
                        @foreach ($listingMeta['intro']['quick_stats'] ?? [] as $stat)
                            @if ($stat['label'] === 'Parduotuvių')
                                {{-- Real action, not a static count — the
                                     store filter right below already lets you
                                     pick one; this just opens it directly
                                     from the hero instead of repeating the
                                     number here too. --}}
                                <button
                                    type="button"
                                    onclick="Livewire.dispatch('open-store-panel')"
                                    class="inline-flex cursor-pointer items-center gap-0.5 whitespace-nowrap font-semibold text-green hover:text-dark-green"
                                >
                                    Keisti parduotuvę
                                    <x-app-icon name="arrow-right" class="size-3 shrink-0" />
                                </button>
                            @else
                                <span>{{ $stat['value'] }} {{ mb_strtolower($stat['label']) }}</span>
                            @endif
                        @endforeach
                        @if (!empty($listingMeta['intro']['freshness_label']))
                            <x-content-freshness :label="$listingMeta['intro']['freshness_label']" />
                        @endif
                    @endif

                    @if ($isStoreHeader)
                        <x-slot:cta>
                            <x-store-subscribe-button />
                        </x-slot:cta>
                    @endif
                </x-type-hero>

                @if ($headerType === 'store')
                    {{-- store_category deliberately does NOT get this —
                         explicit product decision: that page type now uses
                         the shared "Parduotuvės"/"Kategorijos" filter bar
                         below for both switching store and switching
                         category, instead of this pill+its own modal. --}}
                    <x-store-nav-tabs
                        :store-slug="$listingMeta['store_slug']"
                        :leaflets-count="$listingMeta['leaflets_count'] ?? 0"
                        :total-offers="$listingMeta['total_offers'] ?? $total"
                        :categories="$listingMeta['sections']['available_categories'] ?? []"
                        :featured-category="$listingMeta['sections']['featured_category'] ?? null"
                        :all-categories-count="count($listingMeta['sections']['available_categories'] ?? [])"
                        :aria-label="$listingMeta['store_name'] . ' skiltys'"
                        active="akcijos"
                    />
                @elseif ($headerType === 'category')
                    <x-keyword-chips-row :pages="$listingMeta['keyword_pages'] ?? []" />
                @endif
            </div>
        @elseif ($isKeyword)
            {{-- Matches the mockup's keyword-page .type-hero: same shared
                 card, emoji as the icon (real data: KeywordPage::$emoji),
                 eyebrow "Populiari prekė", quick_stats already computed by
                 KeywordPageService::buildQuickStats(). --}}
            <div class="mb-2 flex flex-col gap-4 sm:mb-4">
                @php
                    // The keyword's own category logo (e.g. kava ->
                    // gerimai-kava-arbata.svg) reads as more specific to this
                    // page than the generic "popular" flame icon every
                    // keyword page used to share — falls back to the flame
                    // when a page has no resolvable category (rare).
                    $heroCategorySlug = $listingMeta['keyword_categories'][0]['slug'] ?? null;
                @endphp
                <x-type-hero
                    :title="$pageTitle"
                    :subtitle="$listingMeta['intro']['short_description'] ?? ($listingMeta['intro']['description'] ?? null)"
                    :icon-src="$heroCategorySlug ? '/assets/categories/'.$heroCategorySlug.'.svg' : null"
                    :hide-subtitle-on-mobile="true"
                >
                    @foreach ($listingMeta['intro']['quick_stats'] ?? [] as $stat)
                        @if (!empty($stat['label_first']))
                            <span>{{ mb_strtolower($stat['label']) }} {{ $stat['value'] }}</span>
                        @else
                            <span>{{ $stat['value'] }} {{ mb_strtolower($stat['label']) }}</span>
                        @endif
                    @endforeach
                    @if (!empty($listingMeta['intro']['freshness_label']))
                        <x-content-freshness :label="$listingMeta['intro']['freshness_label']" />
                    @endif

                    @unless ($heroCategorySlug)
                        <x-slot:icon>
                            <x-app-icon name="flame" class="size-7" />
                        </x-slot:icon>
                    @endunless
                </x-type-hero>
            </div>
        @else
            {{-- The plain /akcijos hub has no listing_meta pipeline at all
                 (getAllDiscounts() never built one) — $hubMeta is a small,
                 separate real-data addition (AkcijosController::index()),
                 reusing HomePageMetaService's already-cached stats + the
                 same ContentFreshness mechanism every rich-header page uses,
                 not new content. --}}
            <div class="mb-2 flex flex-col gap-1 sm:mb-4">
                <div class="min-w-0">
                    <h1>{{ $pageTitle }}</h1>
                </div>
                @if ($hubMeta)
                    <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
                        @if (!empty($hubMeta['total_deals_label']) && !empty($hubMeta['active_store_count']))
                            <span>{{ $hubMeta['total_deals_label'] }} aktyvios akcijos iš {{ $hubMeta['active_store_count'] }} parduotuvių.</span>
                        @endif
                        @if (!empty($hubMeta['freshness_label']))
                            <x-content-freshness :label="$hubMeta['freshness_label']" />
                        @endif
                    </p>
                @endif
            </div>
        @endif

        @if ($isKeyword)
            {{-- Mockup shows this grid under its own heading + total count,
                 not dropped in bare — real genitive grammar already computed
                 for the store-comparison heading above, reused here too. --}}
            <div class="mt-6 flex items-center justify-between gap-2">
                <h2 class="text-base font-bold leading-tight text-gray-900 sm:text-lg">Visos {{ $listingMeta['keyword_grammar']['genitive'] ?? '' }} akcijos</h2>
            </div>
        @endif

        @php
            $carouselHtml = ! empty($sections) || ($isStoreHeader && ! empty($topOffers))
                ? view('components.partials.listing-category-carousels', [
                    'sections' => $sections,
                    'primarySlug' => $filtersPrimarySlug,
                    'secondarySlug' => $filtersSecondarySlug,
                    'topOffers' => $isStoreHeader ? $topOffers : [],
                    'availableCategories' => $listingMeta['sections']['available_categories'] ?? [],
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
                :show-store-filter="$showStoreFilter"
                :show-category-filter="$showCategoryFilter"
                :active-store-slug="$activeStoreSlug"
                :active-category-slug="$activeCategorySlug"
                :show-filters="$headerType === 'category' || $headerType === 'store_category' || $isKeyword"
                :key="'filters-'.$basePath"
            />
        @endif

        @php
            // One unified block instead of alternating "card with border" /
            // "plain top-rule divider" / "card with border again" styles —
            // same section-card box throughout, divide-y draws the line
            // between whichever of these five actually have content (not a
            // fixed set of dividers, since any of them can be empty).
            // "Kainos pagal parduotuves" (per-store chips) removed per
            // explicit product decision — superseded by the per-brand
            // summary below, which is the actually-fair comparison (same
            // brand across stores, not each store's own different cheapest
            // product).
            // Per-store table (guarantees every main chain a row) replaced
            // the old per-brand-frequency table per explicit product
            // decision — that table could silently omit a main store
            // entirely (found live: Norfa's cheapest "kava" match has no
            // brand, so Norfa never made the old top-8-by-offer-count list).
            $hasStorePriceTable = !empty($listingMeta['intro']['store_price_table']);
            $hasBottomBlocks = $hasStorePriceTable || !empty($relatedPages) || $seoAboutHtml || !empty($tips) || !empty($faqItems);
            $aboutHeading = $isKeyword
                ? 'Apie ' . mb_strtolower($listingMeta['keyword_grammar']['genitive'] ?? $pageTitle) . ' kainas ir akcijas'
                : 'Apie šias akcijas';
        @endphp

        @if ($hasBottomBlocks)
            <section class="section-card mt-10 w-full divide-y divide-gray-200">
                @if (!empty($listingMeta['intro']['cheapest_answer']))
                    {{-- Direct "kur šiandien pigiausia X" answer — the single
                         cheapest offer across every store, front and center
                         above the brand breakdown, not something a reader has
                         to piece together from a list themselves. --}}
                    @php $answer = $listingMeta['intro']['cheapest_answer']; @endphp
                    <div class="py-6 first:pt-0 last:pb-0">
                        <h2 class="section-heading mb-3">
                            Kur šiandien pigiausia {{ mb_strtolower($listingMeta['keyword_title'] ?? $pageTitle) }}?
                        </h2>
                        <div class="flex flex-wrap items-center gap-4 rounded-xl border border-green-soft-border bg-green-soft px-4 py-3">
                            <x-store-logo :slug="$answer['store_slug']" :name="$answer['store_name']" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Pigiausia dabar</p>
                                <p class="truncate font-bold text-gray-900">{{ $answer['product_name'] }} — {{ $answer['store_name'] }}</p>
                            </div>
                            <span class="text-2xl font-extrabold tabular-nums text-dark-green">{{ number_format($answer['price'], 2, ',', ' ') }}&nbsp;€</span>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-gray-600">
                            Šiuo metu pigiausia {{ mb_strtolower($listingMeta['keyword_title'] ?? $pageTitle) }} —
                            <span class="font-bold text-gray-900">{{ $answer['product_name'] }}</span>
                            parduotuvėje <span class="font-bold text-gray-900">{{ $answer['store_name'] }}</span>,
                            už <span class="font-bold text-dark-green">{{ number_format($answer['price'], 2, ',', ' ') }}&nbsp;€</span>
                            (iš {{ $answer['store_offers_count'] }} galiojančių pasiūlymų šioje parduotuvėje).
                            Iš viso „{{ mb_strtolower($listingMeta['keyword_title'] ?? $pageTitle) }}" akcijos šiuo metu galioja
                            <span class="font-bold text-gray-900">{{ $answer['store_count'] }} {{ \App\Support\LithuanianPlural::offerWord($answer['store_count']) === 'pasiūlymas' ? 'parduotuvėje' : 'parduotuvėse' }}</span>,
                            su <span class="font-bold text-gray-900">{{ number_format($listingMeta['intro']['total_matching_offers'] ?? 0, 0, ',', ' ') }} pasiūlymais</span>.
                            @if (!empty($answer['max_discount_percent']))
                                Didžiausia savaitės nuolaida —
                                <span class="font-bold text-[#c0392b]">-{{ $answer['max_discount_percent'] }}%</span>
                                prekei <span class="font-bold text-gray-900">{{ $answer['max_discount_product'] }}</span>
                                ({{ $answer['max_discount_store'] }}).
                            @endif
                        </p>
                    </div>
                @endif

                @if ($hasStorePriceTable)
                    {{-- Per-store "cheapest right now" — guarantees a row for
                         every main chain (Maxima/Norfa/Lidl/Iki/Rimi) even
                         when its cheapest match has no brand or a low-volume
                         one, still names the real brand/product per row. --}}
                    <div class="py-6 first:pt-0 last:pb-0">
                        <div class="section-heading-row">
                            <h2 class="section-heading">Kainos pagal parduotuvę</h2>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-gray-200">
                            <table class="w-full min-w-[560px] text-left text-sm">
                                <thead>
                                    <tr class="bg-green text-white">
                                        <th class="p-3 font-bold">Parduotuvė</th>
                                        <th class="p-3 font-bold">Pigiausia prekė</th>
                                        <th class="p-3 font-bold">Mažiausia kaina</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($listingMeta['intro']['store_price_table'] as $row)
                                        <tr class="odd:bg-white even:bg-gray-50 hover:bg-green-soft/60">
                                            <td class="p-3">
                                                <span class="font-bold text-gray-900">{{ $row['store_name'] }}</span>
                                            </td>
                                            <td class="p-3">
                                                <a href="{{ $row['product_href'] }}" class="flex min-w-0 items-center gap-3">
                                                    <span class="relative size-12 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                                        @if ($row['product_image_url'])
                                                            <img src="{{ $row['product_image_url'] }}" alt="{{ $row['product_name'] }}" loading="lazy" class="h-full w-full object-contain p-1">
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0">
                                                        @if ($row['brand'])
                                                            <span class="block truncate font-bold text-gray-900">{{ $row['brand'] }}</span>
                                                        @endif
                                                        <span class="block truncate text-xs text-gray-500">{{ $row['product_name'] }}</span>
                                                    </span>
                                                </a>
                                            </td>
                                            <td class="p-3">
                                                <span class="block font-bold tabular-nums text-gray-900">{{ number_format($row['min_price'], 2, ',', ' ') }}&nbsp;€</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (!empty($listingMeta['intro']['store_keyword_sentence']))
                            {{-- The one place a literal "{store} + keyword + akcija"
                                 phrase pairing appears in visible text — targets
                                 store+keyword long-tail queries (e.g. "norfa kava
                                 akcija") the table/chips alone don't. --}}
                            <p class="mt-3 rounded-xl border border-green-soft-border bg-green-soft px-4 py-3 text-sm leading-relaxed text-gray-700">
                                {{ $listingMeta['intro']['store_keyword_sentence'] }}
                            </p>
                        @endif
                    </div>
                @endif

                @if (!empty($relatedPages))
                    {{-- Real, indexable cross-links to sibling keyword pages
                         sharing this page's category (e.g. kava ->
                         malta-kava/tirpi-kava/kavos-kapsules) — unlike the
                         removed "Dažniausiai ieškoma" chips, every link here
                         points at its own real page, no nofollow. These
                         pages already existed and were live, just orphaned:
                         nothing on the site linked to them. --}}
                    <div class="py-6 first:pt-0 last:pb-0">
                        <div class="section-heading-row">
                            <h2 class="section-heading">Susijusios akcijos</h2>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                            @foreach ($relatedPages as $related)
                                <a href="{{ $related['href'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-800 transition-colors hover:border-green/40">
                                    @if ($related['emoji'])
                                        <span class="shrink-0 text-lg" aria-hidden="true">{{ $related['emoji'] }}</span>
                                    @endif
                                    <span class="truncate">{{ $related['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($seoAboutHtml)
                    <div class="py-6 first:pt-0 last:pb-0">
                        {{-- Heading only for keyword pages ("Apie {kava} kainas ir
                             akcijas") — kept OUTSIDE the .prose wrapper below since
                             Tailwind Typography's own h2 size overrode
                             section-heading when nested inside it. Store/category/
                             store_category skip the heading entirely: that content
                             (Store::description/Category::description) is
                             admin-authored prose that already opens with its own
                             heading, so a generic "Apie šias akcijas" label above
                             it just repeated the same idea twice. --}}
                        @if ($isKeyword)
                            <h2 class="section-heading mb-3">{{ $aboutHeading }}</h2>
                        @endif
                        {{-- [&>*:first-child]:mt-0: this admin-authored HTML
                             (Store::description/Category::description) already
                             ships its own explicitly-styled heading (text-2xl/3xl
                             etc) — Tailwind Typography's default ~2em top margin
                             on that h2 wasn't being zeroed by its own first-child
                             reset here, leaving a large gap above it. --}}
                        <div class="category-description max-w-none text-sm prose prose-sm [&>p]:mb-4 [&>p:last-child]:mb-0 [&_:first-child]:mt-0! [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                            {!! $seoAboutHtml !!}
                        </div>
                    </div>
                @endif

                @if (!empty($tips))
                    <div class="py-6 first:pt-0 last:pb-0">
                        <h2 class="section-heading mb-3">Patarimai pirkėjams</h2>
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
                    </div>
                @endif

                @if (!empty($faqItems))
                    <div class="py-6 first:pt-0 last:pb-0">
                        <h2 class="section-heading mb-4">Dažniausiai užduodami klausimai</h2>
                        <x-faq-accordion :items="$faqItems" />
                    </div>
                @endif
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
