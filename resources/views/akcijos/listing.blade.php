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
    $seoAboutHtml = $isKeyword ? ($listingMeta['intro']['seo_about'] ?? null) : null;
    $primarySearchTerm = $listingMeta['keyword_primary_search_term'] ?? ($listingMeta['keyword_slug'] ?? '');
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
            {{-- Ported from leidinys/{store} hub page's header (logo + title,
                 stats subtitle, description, scrollable chip row) — same
                 principle reused here, with content adapted per page type:
                 store pages get the Leidiniai/Akcijos/categories nav-tabs
                 (identical to the leaflet hub page), category pages get
                 related keyword-page chips instead (there's no store-style
                 "Leidiniai" tab to switch to for a category). --}}
            <div class="mb-2 flex flex-col gap-4 sm:mb-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-white p-1.5 shadow-sm sm:size-12">
                            @if ($isStoreHeader)
                                <img src="/assets/stores/{{ $listingMeta['store_slug'] }}.svg" alt="" class="h-full w-full object-contain">
                            @else
                                <img src="/assets/categories/{{ $listingMeta['category_slug'] }}.svg" alt="" class="h-full w-full object-contain" onerror="this.style.visibility='hidden'">
                            @endif
                        </span>
                        <h1 class="min-w-0 font-semibold text-gray-900 sm:truncate">{{ $pageTitle }}</h1>
                    </div>

                    @if ($isStoreHeader)
                        <x-store-subscribe-button class="w-full justify-center sm:w-auto" />
                    @endif
                </div>

                <p class="text-xs text-gray-500 sm:text-sm">
                    @if ($isStoreHeader)
                        @if ($total > 0)
                            {{ number_format($total, 0, ',', ' ') }} aktyvios {{ $listingMeta['store_name'] }} akcijos ·
                        @endif
                        {{ \App\Support\StoreSocialProof::followerLabel($listingMeta['store_slug'], $listingMeta['store_name']) }}
                        @if ($lastUpdated = \App\Support\StoreDataFreshness::lastUpdatedLabel($listingMeta['store_name']))
                            · {{ $lastUpdated }}
                        @endif
                        ·
                        <a href="/leidinys/{{ $listingMeta['store_slug'] }}" class="inline-flex items-center gap-0.5 font-semibold text-green hover:text-dark-green">
                            Žiūrėti visus leidinius
                            <x-app-icon name="arrow-right" class="size-3 shrink-0" />
                        </a>
                    @else
                        {{ collect($listingMeta['intro']['quick_stats'] ?? [])->map(fn ($stat) => $stat['value'] . ' ' . mb_strtolower($stat['label']))->join(' · ') }}
                    @endif
                </p>

                @if (!empty($listingMeta['intro']['description']))
                    <p class="text-sm leading-relaxed text-gray-600 sm:text-base">{{ $listingMeta['intro']['description'] }}</p>
                @endif

                @if ($isStoreHeader)
                    <x-store-nav-tabs
                        :store-slug="$listingMeta['store_slug']"
                        :leaflets-count="$listingMeta['leaflets_count'] ?? 0"
                        :total-offers="$listingMeta['total_offers'] ?? $total"
                        :categories="$listingMeta['sections']['top_categories'] ?? []"
                        :aria-label="$listingMeta['store_name'] . ' skiltys'"
                        active="akcijos"
                    />
                @else
                    <x-keyword-chips-row :pages="$listingMeta['keyword_pages'] ?? []" />
                @endif
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

        <livewire:discount-filters
            :mode="$filtersMode"
            :primary-slug="$filtersPrimarySlug"
            :secondary-slug="$filtersSecondarySlug"
            :initial-deals="$deals"
            :initial-pagination="$pagination"
            :initial-sections="$sections ?? []"
            :sidebar-mode="$sidebarMode"
            :primary-store-name="$listingMeta['store_name'] ?? null"
            :key="'filters-'.$basePath"
        />

        @if (!empty($storeComparisonRows))
            <section class="mt-10 w-full border-t border-gray-200 pt-6">
                <h2 class="mb-2.5 text-base font-bold leading-tight text-gray-900 sm:mb-3 sm:text-lg">{{ $storeComparisonHeading }}</h2>
                <div class="scroll-cards-x relative flex min-w-0 gap-2.5 overflow-x-auto pr-0 sm:gap-3 sm:pr-4">
                    @foreach ($storeComparisonRows as $index => $row)
                        <a
                            href="/akcijos/paieska/{{ rawurlencode($primarySearchTerm) }}?store={{ $row['store_slug'] }}"
                            class="relative flex min-w-[148px] shrink-0 flex-col items-center rounded-xl border bg-white px-2.5 py-2.5 text-center transition-colors sm:min-w-[160px] sm:px-3 sm:py-3 {{ $index === 0 ? 'border-green/40 bg-green/5' : 'border-gray-200 hover:border-green/30' }}"
                        >
                            <div class="flex h-10 items-center justify-center sm:h-11">
                                <x-store-logo :slug="$row['store_slug']" size="md" />
                            </div>
                            <p class="mb-1 text-sm font-bold leading-tight text-gray-900 sm:text-base">{{ $row['store'] }}</p>
                            <div class="mb-1.5">
                                @if (!empty($row['min_price']))
                                    <p class="flex items-baseline justify-center gap-1 leading-tight">
                                        <span class="text-xs font-medium text-gray-500 sm:text-sm">Nuo</span>
                                        <span class="text-xl font-extrabold tabular-nums text-green sm:text-2xl">{{ number_format($row['min_price'], 2, ',', ' ') }} €</span>
                                    </p>
                                @elseif (!empty($row['max_discount_percent']))
                                    {{-- Not run through <x-discount-badge>: that component applies
                                         the >=20% promotion threshold, but this badge always shows
                                         the store's actual max discount regardless of size. --}}
                                    <p class="flex items-baseline justify-center gap-1 leading-tight">
                                        <span class="text-xs font-medium text-gray-500 sm:text-sm">iki</span>
                                        <span class="inline-flex h-[1.6rem] items-center rounded-lg bg-[#ffdb4d] px-2.5 text-base font-bold leading-none text-gray-900 sm:h-8">-{{ $row['max_discount_percent'] }}%</span>
                                    </p>
                                @else
                                    <p class="text-sm font-semibold text-gray-500">Tik nuolaida</p>
                                @endif
                            </div>
                            <span class="rounded-full bg-green/10 px-2 py-0.5 text-xs font-medium text-green">{{ $row['offers_count'] }} {{ \App\Support\LithuanianPlural::offerWord($row['offers_count']) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (!empty($searchExamples) || !empty($keywordCategories))
            <section class="mt-10 w-full border-t border-gray-200 pt-6">
                <h2 class="mb-3 text-base font-bold leading-tight text-gray-900 sm:text-lg">Dažniausiai ieškoma</h2>
                <ul class="flex flex-col gap-1.5 text-sm">
                    @foreach ($searchExamples as $term)
                        <li><a href="/akcijos/paieska/{{ rawurlencode($term) }}" class="text-gray-700 transition-colors hover:text-green">{{ $term }}</a></li>
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
        @elseif (!empty($seo['seo_description']) && !$isKeyword)
            {{-- seo_description carries rich HTML body copy (e.g. Store::description),
                 distinct from the plain-text seo.meta_description used in <meta>.
                 Excluded for keyword pages too — KeywordPageDynamicMetaService sets
                 it to the same intro_html text already shown in "Apie šias akcijas". --}}
            {{-- prose/prose-sm (Tailwind Typography), not a manual text-gray-600 —
                 that greyed out the H2 the raw HTML embeds along with the body
                 copy; prose gives headings and paragraphs distinct colors, matching
                 discounts-layout.tsx's exact class list. --}}
            <div class="category-description mt-10 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">{!! $seo['seo_description'] !!}</div>
        @endif
    </div>
</x-layouts.app>
