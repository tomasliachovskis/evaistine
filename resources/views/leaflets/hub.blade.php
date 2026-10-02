@php
    $intro = $listingMeta['intro'] ?? [];
    $sectionsData = $listingMeta['sections'] ?? [];
    $leaflets = $sectionsData['leaflets'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $storeIdForFreshness = \App\Models\Store::where('slug', $storeSlug)->value('id');
    $freshnessLabel = $storeIdForFreshness && ($freshnessDate = \App\Support\ContentFreshness::forStore($storeIdForFreshness))
        ? \App\Support\LithuanianDate::relative($freshnessDate)
        : null;
    $leafletNoun = $storeSlug === 'iki' ? 'leidynys' : 'leidinys';
    // "leidinys" -> "leidiniai" is a stem swap (drop "ys", add "iai"), not a
    // plain suffix append — naively concatenating 'iai' onto the singular
    // produced "leidinysiai" instead of "leidiniai" for every non-iki store.
    $leafletNounPlural = substr($leafletNoun, 0, -2) . 'iai';
    // "Visi {Store} akcijų leidiniai" — no validity date or year in the H1
    // (real per-leaflet dates now live in the meta description instead, see
    // ProductController::generateSeoData()'s 'store_leaflet' case).
    $pageTitle = 'Visi ' . $storeName . ' akcijų ' . $leafletNounPlural;
    $leafletDescription = $listingMeta['leaflet_description'] ?? null;
    $content = $listingMeta['content'] ?? [];
    $aboutParagraphs = $content['about']['paragraphs'] ?? [];
    $aboutHeading = $content['about']['heading'] ?? null;
    $formatParagraphs = $content['format']['paragraphs'] ?? [];
    $formatHeading = $content['format']['heading'] ?? null;
    $tipsParagraphs = $content['tips']['paragraphs'] ?? [];
    $tipsHeading = $content['tips']['heading'] ?? null;
    $otherStores = $sectionsData['other_stores'] ?? [];
    $hasFallbackContent = count($aboutParagraphs) > 0 || count($formatParagraphs) > 0 || count($tipsParagraphs) > 0;
    $hasAbout = ! empty($leafletDescription) || $hasFallbackContent || count($otherStores) > 0;
    // Only the currently-valid leaflets, not the expired archive below them
    // — an ItemList should represent what's actually available now, same
    // reasoning as excluding expired leaflets from the page's main visual
    // section. Real cover images (confirmed live, e.g.
    // /storage/flyers/pages/103/page-1.webp) were previously in no
    // structured data anywhere on this page.
    $activeLeafletsForSchema = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) !== 'expired'));
    $itemListSchema = ! empty($activeLeafletsForSchema) ? \App\Support\ItemListSchema::build(
        $pageTitle,
        collect($activeLeafletsForSchema)->map(fn ($l) => [
            'name' => $l['title'],
            'href' => $l['view_url'] ?? "/leidinys/{$storeSlug}",
            'image' => $l['image_url'] ?? null,
        ])->all()
    ) : null;
@endphp

<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []"
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? $pageTitle)"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($itemListSchema)
            <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @if ($flyerOffersSchema)
            <script type="application/ld+json">{!! json_encode($flyerOffersSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    {{-- Ported from listing-store-hub-page.tsx + store-listing-header.tsx --}}
    <div class="base-container mx-auto flex w-full flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex w-full flex-col gap-4">
            <x-type-hero
                :title="$pageTitle"
            >
                <x-slot:cta>
                    <x-store-subscribe-button :slug="$storeSlug" />
                </x-slot:cta>
            </x-type-hero>
            <x-hero-stats :freshness="$freshnessLabel" />
        </div>

        {{-- Deliberately NOT nested inside the hero wrapper div above —
             that div is only as tall as its own content (~150px), which
             is also its sticky containing block: once scrolled past that
             short box, position:sticky has nothing left to stick within
             and the bar just scrolls away with it instead of pinning to
             the viewport for the rest of the (much taller) page —
             confirmed live 2026-09-17. Needs to be a direct child of this
             full-page-height column instead. --}}
        <x-leaflet-quick-links :store-slug="$storeSlug" :store-name="$storeName" :total-offers="$totalOffers" :shows-discounts-page="$showsDiscountsPage" />

        @php
            // Split rather than just re-sort: an SEO audit flagged expired
            // leaflets sitting in the same grid, same size, as the current
            // one — hard to tell at a glance which leaflet is actually live.
            // A separate, clearly-labeled section makes that unambiguous.
            $activeLeaflets = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) !== 'expired'));
            $expiredLeaflets = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) === 'expired'));
        @endphp

        @if (! empty($activeLeaflets))
            <section>
                <div class="section-heading-row">
                    <h2 class="section-heading">Galiojantys leidiniai</h2>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                    @foreach ($activeLeaflets as $leaflet)
                        <x-leaflet-card :leaflet="$leaflet" :show-store-name="false" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- The current flyers' offers as text: this evergreen URL is the
             one that ranks for "{store} leidinys", and the leaflet cards
             above are images only. Flex-wrap, not CSS grid (see
             livewire/discount-filters.blade.php). --}}
        @if (! empty($flyerOffers['offers']))
            <section aria-labelledby="hub-flyer-offers-heading">
                <div class="section-heading-row">
                    <h2 id="hub-flyer-offers-heading" class="section-heading">
                        Naujausio {{ $storeName }} {{ $storeSlug === 'iki' ? 'leidynio' : 'leidinio' }} akcijos
                        <span class="font-normal text-gray-500">({{ $flyerOffers['total'] }})</span>
                    </h2>
                </div>
                <p class="mb-3 max-w-3xl text-sm leading-relaxed text-gray-600">{{ $flyerOffers['intro'] }}</p>
                <div class="flex w-full flex-wrap gap-2 sm:gap-3">
                    @foreach ($flyerOffers['offers'] as $deal)
                        <x-deal-card
                            :deal="$deal"
                            :stretch="false"
                            :context-store-slug="$storeSlug"
                            :compare-stores="true"
                            source="hub_flyer_offers"
                            class="deal-card-width"
                        />
                    @endforeach
                </div>
                <a href="{{ $flyerOffers['main_flyer']['href'] }}" class="section-link mt-4">
                    Visas {{ $storeSlug === 'iki' ? 'leidynys' : 'leidinys' }} „{{ $flyerOffers['main_flyer']['title'] }}“ – {{ $flyerOffers['main_flyer']['total'] }} {{ \App\Support\LithuanianPlural::offerWord($flyerOffers['main_flyer']['total']) }}
                    <x-app-icon name="chevron-right" class="size-3.5" />
                </a>
            </section>
        @endif

        @if (! empty($expiredLeaflets))
            <section class="mt-6">
                <div class="section-heading-row">
                    <h2 class="section-heading text-gray-500">Pasibaigę leidiniai</h2>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                    @foreach ($expiredLeaflets as $leaflet)
                        <x-leaflet-card :leaflet="$leaflet" :show-store-name="false" class="opacity-75" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($hasAbout)
            <section id="apie" class="scroll-mt-32 rounded-xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="store-hub-about-heading">
                @if (! empty($leafletDescription))
                    {{-- The generated HTML supplies its own <h2> section headings
                         (see DescriptionGenerationService::getStoreLeafletSystemPrompt) —
                         no separate static heading here, that would just duplicate
                         the first one and re-create the "wall of text" problem this
                         structure exists to avoid. --}}
                    <div id="store-hub-about-heading" class="category-description max-w-none text-sm [&_h2]:text-lg! [&_h2]:sm:text-xl! [&_h2]:font-bold! [&_h2]:leading-tight! [&_h2]:text-gray-900! [&>h2]:mt-6! [&>h2:first-child]:mt-0! [&_p]:text-sm [&_p]:leading-relaxed [&_p]:text-gray-600 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1 [&_li]:text-sm [&_li]:leading-relaxed [&_li]:text-gray-600 [&_a]:text-dark-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">{!! $leafletDescription !!}</div>
                @elseif ($hasFallbackContent)
                    @if (count($aboutParagraphs) > 0)
                        <h2 id="store-hub-about-heading" class="text-base font-extrabold text-gray-900 sm:text-lg">{{ $aboutHeading ?? ('Apie ' . $storeName) }}</h2>
                        <div class="mt-2 space-y-2">
                            @foreach ($aboutParagraphs as $paragraph)
                                <p class="text-sm leading-relaxed text-gray-600">{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if (count($formatParagraphs) > 0)
                        <div class="{{ count($aboutParagraphs) > 0 ? 'mt-5' : '' }}">
                            <h2 class="text-base font-extrabold text-gray-900 sm:text-lg">{{ $formatHeading ?? ($storeName . ' leidinio turinys') }}</h2>
                            <div class="mt-2 space-y-2">
                                @foreach ($formatParagraphs as $paragraph)
                                    <p class="text-sm leading-relaxed text-gray-600">{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (count($tipsParagraphs) > 0)
                        <div class="{{ (count($aboutParagraphs) > 0 || count($formatParagraphs) > 0) ? 'mt-5' : '' }}">
                            <h2 class="text-base font-extrabold text-gray-900 sm:text-lg">{{ $tipsHeading ?? ('Kaip skaityti ' . $storeName . ' leidinį') }}</h2>
                            <div class="mt-2 space-y-2">
                                @foreach ($tipsParagraphs as $paragraph)
                                    <p class="text-sm leading-relaxed text-gray-600">{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                @if (count($otherStores) > 0)
                    <div class="{{ (! empty($leafletDescription) || $hasFallbackContent) ? 'mt-5' : '' }}">
                        <h2 class="text-base font-extrabold text-gray-900 sm:text-lg">Kitos parduotuvės</h2>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($otherStores as $otherStore)
                                <a href="{{ $otherStore['href'] }}" class="rounded-full border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:border-gray-300 hover:bg-gray-50 min-h-12 inline-flex items-center">{{ $otherStore['name'] }} leidinys</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
