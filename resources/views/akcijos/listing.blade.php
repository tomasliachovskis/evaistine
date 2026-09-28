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
    // No live offers: KeywordPageService returns alternative_pages (close
    // keyword pages that DO have offers right now) for the empty-state
    // block instead of the grid — the regular "Susijusios akcijos" list is
    // hidden then, it would mostly repeat the same pages.
    $noOffers = $isKeyword && !empty($listingMeta['no_offers']);
    $alternativePages = $noOffers ? ($listingMeta['alternative_pages'] ?? []) : [];
    $relatedPages = $isKeyword && ! $noOffers ? ($listingMeta['related_pages'] ?? []) : [];
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
    @if($requiresAgeVerification ?? false)
        @push('body-end')
            <x-age-verification-modal />
        @endpush
    @endif

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
                    @if ($isStoreHeader)
                        <x-slot:cta>
                            <x-store-subscribe-button />
                        </x-slot:cta>
                    @endif
                </x-type-hero>

                @php
                    // Store / store+category: the real offer count (the
                    // store-wide total on a store page — its grid shows
                    // curated carousels, not every offer). Category: its
                    // quick_stats (store count deliberately left out).
                    $richHeroOffers = $headerType === 'store'
                        ? (int) ($listingMeta['total_offers'] ?? $total)
                        : (int) $total;
                    $richHeroStats = $headerType === 'category'
                        ? array_values(array_filter(
                            $listingMeta['intro']['quick_stats'] ?? [],
                            fn ($stat) => ($stat['label'] ?? '') !== 'Parduotuvių',
                        ))
                        : ($richHeroOffers > 0 ? [[
                            'icon' => 'tag',
                            'pill' => \App\Support\LithuanianPlural::formatCount($richHeroOffers) . ' ' . \App\Support\LithuanianPlural::offerWord($richHeroOffers),
                        ]] : []);
                @endphp
                <x-hero-stats :stats="$richHeroStats" :freshness="$listingMeta['intro']['freshness_label'] ?? null" />

                @if ($headerType === 'category')
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
                    @unless ($heroCategorySlug)
                        <x-slot:icon>
                            <x-app-icon name="flame" class="size-7" />
                        </x-slot:icon>
                    @endunless
                </x-type-hero>
                <x-hero-stats :stats="$listingMeta['intro']['quick_stats'] ?? []" :freshness="$listingMeta['intro']['freshness_label'] ?? null" />
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
                    @php
                        // total_deals_label is pre-formatted ("12 345") by
                        // HomePageMetaService; digits only for the plural.
                        $hubDeals = (int) preg_replace('/\D/', '', (string) ($hubMeta['total_deals_label'] ?? ''));
                        $hubStores = (int) ($hubMeta['active_store_count'] ?? 0);
                        $hubStats = array_values(array_filter([
                            $hubDeals > 0 ? ['icon' => 'tag', 'pill' => $hubMeta['total_deals_label'] . ' ' . \App\Support\LithuanianPlural::offerWord($hubDeals)] : null,
                            $hubStores > 0 ? ['icon' => 'store', 'pill' => $hubStores . ' ' . \App\Support\LithuanianPlural::storeWord($hubStores)] : null,
                        ]));
                    @endphp
                    <x-hero-stats class="mt-1" :stats="$hubStats" :freshness="$hubMeta['freshness_label'] ?? null" />
                @endif
            </div>
        @endif

        @if ($noOffers)
            {{-- No live offers for this keyword right now — the page stays
                 200/indexable (a 404 here cost the URL its rankings every
                 time offers ran out for a week), with a plain notice and
                 links to close keyword pages that have offers today. Same
                 notice box as the store+category $fallbackOtherStores case
                 below. --}}
            <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 sm:p-5">
                <p>
                    Šiuo metu {{ $listingMeta['keyword_grammar']['genitive'] ?? mb_strtolower($pageTitle) }} akcijų nėra. Naujos akcijos atsiranda kas savaitę{{ !empty($alternativePages) ? ' — kol kas pažiūrėkite panašius pasiūlymus:' : '.' }}
                </p>
                @if (!empty($alternativePages))
                    <div class="mt-4">
                        @include('components.partials.related-keyword-links', ['links' => $alternativePages])
                    </div>
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
                    'availableCategories' => $listingMeta['sections']['available_categories'] ?? [],
                ])->render()
                : '';
        @endphp

        @if ($noOffers)
            {{-- Empty-state notice above replaces the grid. --}}
        @elseif ($fallbackOtherStores)
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
                :show-sort="$showSort"
                :active-store-slug="$activeStoreSlug"
                :active-category-slug="$activeCategorySlug"
                :show-filters="$headerType === 'category' || $headerType === 'store_category' || $headerType === 'store' || $isKeyword"
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
                @if (!empty($listingMeta['intro']['cheapest_answer']) || $hasStorePriceTable)
                    {{-- One price block: the direct "kur šiandien pigiausia X"
                         answer as a single sentence, then the per-store table.
                         The table guarantees a row for every main chain
                         (Maxima/Norfa/Lidl/Iki/Rimi) even when its cheapest
                         match has no brand; the cheapest row is highlighted
                         instead of repeated in a separate box above. --}}
                    <div class="py-6 first:pt-0 last:pb-0">
                        <h2 class="section-heading mb-3">
                            Kur šiandien pigiausia {{ mb_strtolower($listingMeta['keyword_title'] ?? $pageTitle) }}?
                        </h2>
                        @if (!empty($listingMeta['intro']['cheapest_answer']))
                            @php $answer = $listingMeta['intro']['cheapest_answer']; @endphp
                            <p class="text-sm leading-relaxed text-gray-600">
                                Šiuo metu pigiausia {{ mb_strtolower($listingMeta['keyword_title'] ?? $pageTitle) }} —
                                <span class="font-bold text-gray-900">{{ $answer['product_name'] }}</span>
                                parduotuvėje <span class="font-bold text-gray-900">{{ $answer['store_name'] }}</span>,
                                už <span class="font-bold text-dark-green">{{ number_format($answer['price'], 2, ',', ' ') }}&nbsp;€</span>@if ($answer['store_offers_count'] > 1)
                                    (iš {{ $answer['store_offers_count'] }} galiojančių pasiūlymų šioje parduotuvėje)@endif.
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
                        @endif

                        @if ($hasStorePriceTable)
                            @php
                                $storePriceRows = $listingMeta['intro']['store_price_table'];
                                $cheapestStorePrice = min(array_column($storePriceRows, 'min_price'));
                            @endphp
                            {{-- No min-width/horizontal scroll: the price column
                                 must stay visible on a phone — it's what the
                                 table is for. Product name truncates instead. --}}
                            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                                <table class="w-full table-fixed text-left text-sm">
                                    <caption class="sr-only">{{ mb_convert_case(mb_substr($listingMeta['keyword_grammar']['genitive'] ?? '', 0, 1), MB_CASE_UPPER, 'UTF-8') . mb_substr($listingMeta['keyword_grammar']['genitive'] ?? '', 1) }} kainos pagal parduotuvę</caption>
                                    <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th scope="col" class="w-24 p-3 sm:w-40">Parduotuvė</th>
                                            <th scope="col" class="p-3">Pigiausia prekė</th>
                                            <th scope="col" class="w-20 p-3 text-right sm:w-24">Kaina</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($storePriceRows as $row)
                                            @php $isCheapestRow = $row['min_price'] <= $cheapestStorePrice; @endphp
                                            <tr class="{{ $isCheapestRow ? 'bg-green-soft' : 'bg-white' }}">
                                                <td class="p-3">
                                                    <span class="flex flex-col items-start gap-1">
                                                        <x-store-logo :slug="$row['store_slug']" :name="$row['store_name']" size="sm" />
                                                        <span class="text-xs font-semibold leading-tight text-gray-600">{{ $row['store_name'] }}</span>
                                                    </span>
                                                </td>
                                                <td class="p-3">
                                                    <a href="{{ $row['product_href'] }}" class="flex min-w-0 items-center gap-3">
                                                        <span class="relative hidden size-10 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-white sm:block">
                                                            @if ($row['product_image_url'])
                                                                <img src="{{ $row['product_image_url'] }}" alt="{{ $row['product_name'] }}" loading="lazy" class="h-full w-full object-contain p-1">
                                                            @endif
                                                        </span>
                                                        <span class="min-w-0">
                                                            @if ($row['brand'])
                                                                <span class="block truncate font-bold text-gray-900">{{ $row['brand'] }}</span>
                                                            @endif
                                                            <span class="line-clamp-2 text-sm sm:line-clamp-1 {{ $row['brand'] ? 'text-gray-600' : 'font-semibold text-gray-900' }}">{{ $row['product_name'] }}</span>
                                                        </span>
                                                    </a>
                                                </td>
                                                <td class="p-3 text-right">
                                                    <span class="block font-bold tabular-nums {{ $isCheapestRow ? 'text-dark-green' : 'text-gray-900' }}">{{ number_format($row['min_price'], 2, ',', ' ') }}&nbsp;€</span>
                                                    @if ($isCheapestRow)
                                                        <span class="block text-xs font-bold text-dark-green">Pigiausia</span>
                                                    @endif
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
                                     akcija") the table alone doesn't. Plain text, not
                                     a highlighted box: it restates the table. --}}
                                <p class="mt-3 text-sm leading-relaxed text-gray-600">
                                    {{ $listingMeta['intro']['store_keyword_sentence'] }}
                                </p>
                            @endif
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
                        @include('components.partials.related-keyword-links', ['links' => $relatedPages])
                    </div>
                @endif

                @if (!empty($faqItems))
                    <div class="py-6 first:pt-0 last:pb-0">
                        <h2 class="section-heading mb-4">Dažniausiai užduodami klausimai</h2>
                        <x-faq-accordion :items="$faqItems" />
                    </div>
                @endif

                @if ($seoAboutHtml || !empty($tips))
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
                        @if ($seoAboutHtml)
                            {{-- [&>*:first-child]:mt-0: this admin-authored HTML
                                 (Store::description/Category::description) already
                                 ships its own explicitly-styled heading (text-2xl/3xl
                                 etc) — Tailwind Typography's default ~2em top margin
                                 on that h2 wasn't being zeroed by its own first-child
                                 reset here, leaving a large gap above it. --}}
                            <div class="category-description max-w-none text-sm prose prose-sm [&>p]:mb-4 [&>p:last-child]:mb-0 [&_:first-child]:mt-0! [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                                {!! $seoAboutHtml !!}
                            </div>
                        @endif
                        @if (!empty($tips))
                            {{-- Buying tips folded into the "Apie" block as a plain
                                 list (was its own section of bordered cards). The
                                 GPT-generated tips[].icon emoji is deliberately not
                                 rendered — no emoji in UI. --}}
                            <h3 class="mt-5 mb-2 text-base font-bold text-gray-900">Patarimai pirkėjams</h3>
                            <ul class="space-y-2.5">
                                @foreach ($tips as $tip)
                                    <li class="flex gap-2.5 text-sm leading-relaxed">
                                        <x-app-icon name="check" class="mt-0.5 size-4 shrink-0 text-green" />
                                        <p class="text-gray-600"><span class="font-semibold text-gray-900">{{ $tip['title'] }}{{ preg_match('/[.!?:]$/u', $tip['title']) ? '' : '.' }}</span> {{ $tip['text'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
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
