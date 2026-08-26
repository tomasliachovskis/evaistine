@php
    $intro = $listingMeta['intro'] ?? [];
    $sectionsData = $listingMeta['sections'] ?? [];
    $leaflets = $sectionsData['leaflets'] ?? [];
    $faq = $sectionsData['faq'] ?? [];
    $topCategories = $sectionsData['top_categories'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $leafletNoun = $storeSlug === 'iki' ? 'leidynys' : 'leidinys';
    $pageTitle = $storeName . ' ' . $leafletNoun . 'iai';
    $seoAboutParagraphs = array_values(array_filter(explode("\n\n", $intro['seo_about'] ?? '')));
    $hasAbout = count($seoAboutParagraphs) > 0 || count($faq) > 0;

    // Ported from lib/store-social-proof.ts's resolveStoreFollowerCount — a
    // deterministic (not random) per-store "social proof" number, same hash
    // function so it matches what the original showed for each store.
    $followerOptions = [842, 1247, 1784, 2136, 2891, 3420, 4156, 5284, 6731, 8420, 9124, 10583, 12470, 14236, 15842, 17620, 19405, 21340, 23891, 26750];
    $hash = 0;
    foreach (str_split($storeSlug) as $ch) {
        $hash = ($hash * 31 + ord($ch)) & 0xFFFFFFFF;
    }
    $followerCount = $followerOptions[$hash % count($followerOptions)];
    $followerNoun = match (true) {
        $followerCount % 10 === 1 && $followerCount % 100 !== 11 => 'žmogus',
        in_array($followerCount % 10, [2, 3, 4, 5, 6, 7, 8, 9]) && !in_array($followerCount % 100, range(11, 19)) => 'žmonės',
        default => 'žmonių',
    };
    $followersLabel = number_format($followerCount, 0, ',', ' ') . " {$followerNoun} jau seka {$storeName} akcijas";

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
                        {{ number_format($totalOffers, 0, ',', ' ') }} aktyvios {{ $storeName }} akcijos ·
                    @endif
                    {{ $followersLabel }} ·
                    <a href="/akcijos/{{ $storeSlug }}" class="inline-flex items-center gap-0.5 font-semibold text-green hover:text-dark-green">
                        Žiūrėti visas akcijas
                        <x-app-icon name="arrow-right" class="size-3 shrink-0" />
                    </a>
                </p>
                @if (!empty($intro['description']))
                    <p class="mt-3 text-sm leading-relaxed text-gray-600 sm:text-base">{{ $intro['description'] }}</p>
                @endif
            </div>

            <nav aria-label="{{ $storeName }} skiltys" class="scroll-cards-x flex flex-nowrap items-center gap-2.5 sm:flex-wrap sm:overflow-visible">
                <a href="/leidinys/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white">
                    Leidiniai
                    <x-count-pill :count="count($leaflets)" color="white" />
                </a>
                <a href="/akcijos/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green px-4 py-2 text-base font-bold text-green transition-colors hover:bg-green/5">
                    Akcijos
                    <x-count-pill :count="$totalOffers" color="green" />
                </a>
                @if (count($topCategories) > 0)
                    <span class="mx-0.5 hidden h-6 w-px shrink-0 bg-gray-300 sm:block" aria-hidden="true"></span>
                    @foreach (array_slice($topCategories, 0, 5) as $category)
                        <a href="{{ $category['href'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-base font-semibold text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                            {{ $category['name'] }}
                            <x-count-pill :count="$category['offers_count'] ?? 0" color="gray" />
                        </a>
                    @endforeach
                @endif
            </nav>
        </div>

        @if (!empty($leaflets))
            <section>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                    @foreach ($leaflets as $leaflet)
                        @php
                            $isExpired = ($leaflet['status'] ?? null) === 'expired';
                            $href = $leaflet['view_url'] ?? "/leidinys/{$storeSlug}";
                            $days = $leaflet['days_remaining'] ?? null;
                        @endphp
                        <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">
                            <a href="{{ $href }}" class="relative block aspect-[6/5] w-full overflow-hidden bg-gray-50">
                                @if (!empty($leaflet['image_url']))
                                    <img src="{{ $leaflet['image_url'] }}" alt="{{ $leaflet['title'] ?? $storeName }}" loading="lazy" class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03] {{ $isExpired ? 'grayscale' : '' }}">
                                @endif
                            </a>
                            <div class="flex flex-1 flex-col gap-2 p-4">
                                <p class="line-clamp-2 text-sm font-semibold text-gray-900">{{ $leaflet['title'] ?? '' }}</p>
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

        @if (count($sections ?? []))
            <div class="flex min-w-0 flex-col">
                @foreach ($sections as $section)
                    <x-landing-deals-section
                        :id="'category-'.$section['slug']"
                        :title="$section['name']"
                        :deals="$section['discounts']"
                        icon="shopping-basket"
                        layout="carousel"
                        :see-all-href="'/akcijos/'.$storeSlug.'/'.$section['slug']"
                    />
                @endforeach
            </div>
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
