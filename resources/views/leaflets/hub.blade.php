@php
    $intro = $listingMeta['intro'] ?? [];
    $sectionsData = $listingMeta['sections'] ?? [];
    $leaflets = $sectionsData['leaflets'] ?? [];
    $faq = $sectionsData['faq'] ?? [];
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
    $seoAboutParagraphs = array_values(array_filter(explode("\n\n", $intro['seo_about'] ?? '')));
    $hasAbout = count($seoAboutParagraphs) > 0 || count($faq) > 0;
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
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    {{-- Ported from listing-store-hub-page.tsx + store-listing-header.tsx --}}
    <div class="base-container mx-auto flex w-full flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex w-full flex-col gap-4">
            <x-type-hero
                :title="$pageTitle"
            >
                @if ($freshnessLabel)
                    <x-content-freshness :label="$freshnessLabel" />
                @endif

                <x-slot:cta>
                    <x-store-subscribe-button />
                </x-slot:cta>
            </x-type-hero>
        </div>

        {{-- Deliberately NOT nested inside the hero wrapper div above —
             that div is only as tall as its own content (~150px), which
             is also its sticky containing block: once scrolled past that
             short box, position:sticky has nothing left to stick within
             and the bar just scrolls away with it instead of pinning to
             the viewport for the rest of the (much taller) page —
             confirmed live 2026-09-17. Needs to be a direct child of this
             full-page-height column instead. --}}
        <x-leaflet-quick-links :store-slug="$storeSlug" :store-name="$storeName" :total-offers="$totalOffers" />

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

        @if (! empty($topOffers))
            <x-landing-deals-section
                id="geriausi-pasiulymai"
                title="Geriausi savaitės pasiūlymai"
                :deals="$topOffers"
                icon="flame"
                layout="carousel"
            />
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
            <section id="apie" class="scroll-mt-24 rounded-xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="store-hub-about-heading">
                @if (count($seoAboutParagraphs) > 0)
                    <h2 id="store-hub-about-heading" class="text-base font-extrabold text-gray-900 sm:text-lg">Apie {{ $storeName }} leidinį ir akcijas</h2>
                    <div class="mt-2 space-y-2">
                        @foreach ($seoAboutParagraphs as $paragraph)
                            <p class="text-sm leading-relaxed text-gray-600">{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif

                @if (count($faq) > 0)
                    <div class="{{ count($seoAboutParagraphs) > 0 ? 'mt-5' : '' }}">
                        <x-faq-accordion :items="$faq" layout="single" />
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
