@php
    $intro = $listingMeta['intro'] ?? [];
    $sectionsData = $listingMeta['sections'] ?? [];
    $leaflets = $sectionsData['leaflets'] ?? [];
    $faq = $sectionsData['faq'] ?? [];
    $topCategories = $sectionsData['top_categories'] ?? [];
    $featuredCategory = $sectionsData['featured_category'] ?? null;
    $allCategoriesCount = count($sectionsData['available_categories'] ?? []);
    $locationsCount = $listingMeta['locations_count'] ?? 0;
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $leafletNoun = $storeSlug === 'iki' ? 'leidynys' : 'leidinys';
    // "leidinys" -> "leidiniai" is a stem swap (drop "ys", add "iai"), not a
    // plain suffix append — naively concatenating 'iai' onto the singular
    // produced "leidinysiai" instead of "leidiniai" for every non-iki store.
    $leafletNounPlural = substr($leafletNoun, 0, -2) . 'iai';
    $pageTitle = $storeName . ' ' . $leafletNounPlural;
    $seoAboutParagraphs = array_values(array_filter(explode("\n\n", $intro['seo_about'] ?? '')));
    $hasAbout = count($seoAboutParagraphs) > 0 || count($faq) > 0;

    $daysWord = fn ($n) => match (true) {
        $n === 1 => 'diena',
        $n % 10 >= 2 && $n % 10 <= 9 && !($n % 100 >= 11 && $n % 100 <= 19) => 'dienas',
        default => 'dienų',
    };
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? $pageTitle)"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    {{-- Ported from listing-store-hub-page.tsx + store-listing-header.tsx --}}
    <div class="base-container mx-auto flex w-full flex-col gap-6 pb-8 pt-2 sm:gap-8 sm:pb-10 sm:pt-3">
        <div class="flex w-full flex-col gap-4">
            <div class="min-w-0">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-white p-1.5 shadow-sm sm:size-12">
                            <img src="/assets/stores/{{ $storeSlug }}.svg" alt="" class="h-full w-full object-contain">
                        </span>
                        <h1 class="min-w-0 truncate font-semibold text-gray-900">{{ $pageTitle }}</h1>
                    </div>

                    <x-store-subscribe-button />
                </div>

                <p class="mt-1.5 text-xs text-gray-500 sm:text-sm">
                    @if ($totalOffers > 0)
                        {{ number_format($totalOffers, 0, ',', ' ') }} aktyvios {{ $storeName }} akcijos
                    @endif
                    @if ($lastUpdated = \App\Support\StoreDataFreshness::lastUpdatedLabel($storeName))
                        @if ($totalOffers > 0) · @endif {{ $lastUpdated }}
                    @endif
                    @if ($locationsCount > 0)
                        ·
                        <a href="/parduotuves/{{ $storeSlug }}" class="inline-flex items-center gap-0.5 font-semibold text-green hover:text-dark-green">
                            {{ number_format($locationsCount, 0, ',', ' ') }} parduotuvės Lietuvoje
                            <x-app-icon name="arrow-right" class="size-3 shrink-0" />
                        </a>
                    @endif
                </p>
                @if (!empty($intro['description']))
                    <p class="mt-3 text-sm leading-relaxed text-gray-600 sm:text-base">{{ $intro['description'] }}</p>
                @endif
            </div>

            <x-store-nav-tabs
                :store-slug="$storeSlug"
                :leaflets-count="count($leaflets)"
                :total-offers="$totalOffers"
                :categories="$topCategories"
                :featured-category="$featuredCategory"
                :all-categories-count="$allCategoriesCount"
                :aria-label="$storeName . ' skiltys'"
                active="leidiniai"
            />
        </div>

        @php
            // Split rather than just re-sort: an SEO audit flagged expired
            // leaflets sitting in the same grid, same size, as the current
            // one — hard to tell at a glance which leaflet is actually live.
            // A separate, clearly-labeled section makes that unambiguous.
            $activeLeaflets = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) !== 'expired'));
            $expiredLeaflets = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) === 'expired'));

            $formatLeafletDateRange = function (array $leaflet) {
                if (empty($leaflet['valid_from']) || empty($leaflet['valid_to'])) {
                    return null;
                }

                return \Illuminate\Support\Carbon::parse($leaflet['valid_from'])->format('Y.m.d')
                    . ' – ' . \Illuminate\Support\Carbon::parse($leaflet['valid_to'])->format('Y.m.d');
            };
        @endphp

        @if (! empty($activeLeaflets))
            <section>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                    @foreach ($activeLeaflets as $leaflet)
                        @php
                            $isExpired = ($leaflet['status'] ?? null) === 'expired';
                            $href = $leaflet['view_url'] ?? "/leidinys/{$storeSlug}";
                            $days = $leaflet['days_remaining'] ?? null;
                            $dateRange = $formatLeafletDateRange($leaflet);
                        @endphp
                        <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">
                            <a href="{{ $href }}" class="relative block aspect-[6/5] w-full overflow-hidden bg-gray-50">
                                @if (!empty($leaflet['thumbnail_url'] ?? $leaflet['image_url'] ?? null))
                                    <img src="{{ $leaflet['thumbnail_url'] ?? $leaflet['image_url'] }}" alt="{{ $leaflet['title'] ?? $storeName }}" loading="lazy" class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03] {{ $isExpired ? 'grayscale' : '' }}">
                                @endif
                            </a>
                            <div class="flex flex-1 flex-col gap-2 p-4">
                                <p class="line-clamp-2 text-sm font-semibold text-gray-900">{{ $leaflet['title'] ?? '' }}</p>
                                @if ($dateRange)
                                    {{-- A consistent, real identifier for every card — some
                                         leaflets carry a themed campaign name instead of a
                                         sequential number (e.g. "Skonių dienos"), so a fixed
                                         "Nr. X" can't be shown for all of them without
                                         fabricating one; the validity date range is always
                                         real and always available. --}}
                                    <p class="text-xs font-medium text-gray-500">{{ $dateRange }}</p>
                                @endif
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $isExpired ? 'text-gray-400' : ($days !== null && $days <= 2 ? 'text-red-600' : 'text-dark-green') }}">
                                    <x-app-icon name="clock" class="size-3.5" />
                                    {{ $isExpired ? 'Nebegalioja' : ($days !== null ? "Galioja dar {$days} {$daysWord($days)}" : 'Galioja') }}
                                </span>
                                <a href="{{ $href }}" class="mt-auto inline-flex h-9 w-full items-center justify-center rounded-lg bg-green text-sm font-bold text-white hover:bg-dark-green">Peržiūrėti</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($topOffers))
            <x-landing-deals-section
                id="geriausi-pasiulymai"
                title="Geriausi pasiūlymai"
                :deals="$topOffers"
                icon="flame"
                layout="carousel"
            />
        @endif

        @if (! empty($expiredLeaflets))
            <section class="mt-8">
                <h2 class="mb-3 text-base font-bold text-gray-900">Pasibaigę leidiniai</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                    @foreach ($expiredLeaflets as $leaflet)
                        @php
                            $isExpired = ($leaflet['status'] ?? null) === 'expired';
                            $href = $leaflet['view_url'] ?? "/leidinys/{$storeSlug}";
                            $days = $leaflet['days_remaining'] ?? null;
                            $dateRange = $formatLeafletDateRange($leaflet);
                        @endphp
                        <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">
                            <a href="{{ $href }}" class="relative block aspect-[6/5] w-full overflow-hidden bg-gray-50">
                                @if (!empty($leaflet['thumbnail_url'] ?? $leaflet['image_url'] ?? null))
                                    <img src="{{ $leaflet['thumbnail_url'] ?? $leaflet['image_url'] }}" alt="{{ $leaflet['title'] ?? $storeName }}" loading="lazy" class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03] {{ $isExpired ? 'grayscale' : '' }}">
                                @endif
                            </a>
                            <div class="flex flex-1 flex-col gap-2 p-4">
                                <p class="line-clamp-2 text-sm font-semibold text-gray-900">{{ $leaflet['title'] ?? '' }}</p>
                                @if ($dateRange)
                                    <p class="text-xs font-medium text-gray-500">{{ $dateRange }}</p>
                                @endif
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $isExpired ? 'text-gray-400' : ($days !== null && $days <= 2 ? 'text-red-600' : 'text-dark-green') }}">
                                    <x-app-icon name="clock" class="size-3.5" />
                                    {{ $isExpired ? 'Nebegalioja' : ($days !== null ? "Galioja dar {$days} {$daysWord($days)}" : 'Galioja') }}
                                </span>
                                <a href="{{ $href }}" class="mt-auto inline-flex h-9 w-full items-center justify-center rounded-lg bg-green text-sm font-bold text-white hover:bg-dark-green">Peržiūrėti</a>
                            </div>
                        </article>
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
