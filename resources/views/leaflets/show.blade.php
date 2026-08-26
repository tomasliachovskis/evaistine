@php
    $flyer = $listingMeta['flyer'];
    $pages = $listingMeta['pages'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $otherLeaflets = collect($listingMeta['leaflets'] ?? [])->filter(fn ($l) => ($l['slug'] ?? null) !== $flyer['slug'])->values();
    $dateRange = ($flyer['valid_from'] ?? null) && ($flyer['valid_to'] ?? null)
        ? \Illuminate\Support\Carbon::parse($flyer['valid_from'])->translatedFormat('Y-m-d') . ' – ' . \Illuminate\Support\Carbon::parse($flyer['valid_to'])->translatedFormat('Y-m-d')
        : null;
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? $flyer['title'])"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <main class="base-container py-6 sm:py-8">
        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-gray-100 bg-white p-1 shadow-sm">
                            <img src="/assets/stores/{{ $storeSlug }}.svg" alt="" class="h-full w-full object-contain">
                        </span>
                        <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $flyer['title'] }}</h1>
                    </div>
                    @if ($dateRange)
                        <p class="mt-1.5 text-sm text-gray-600">{{ $dateRange }}</p>
                    @endif
                </div>
                {{-- The raw PDF is scrape/OCR source material, never a user-facing
                     download — production shows a "Sekti akcijas" follow CTA here
                     instead, never exposing the PDF URL at all. --}}
                <x-store-subscribe-button />
            </div>

            {{-- Ported from store-listing-header.tsx's nav row — plain links to
                 the store's leaflet hub / full discount listing / top categories,
                 not tabs that swap content in place. --}}
            <nav aria-label="{{ $storeName }} skiltys" class="scroll-cards-x flex flex-nowrap items-center gap-2.5 sm:flex-wrap sm:overflow-visible">
                <a href="/leidinys/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white">
                    Leidiniai
                    <x-count-pill :count="$listingMeta['leaflets_count'] ?? 0" color="white" />
                </a>
                <a href="/akcijos/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green px-4 py-2 text-base font-bold text-green transition-colors hover:bg-green/5">
                    Akcijos
                    <x-count-pill :count="$totalOffers" color="green" />
                </a>
                @if (!empty($listingMeta['top_categories']))
                    <span class="mx-0.5 hidden h-6 w-px shrink-0 bg-gray-300 sm:block" aria-hidden="true"></span>
                    @foreach (array_slice($listingMeta['top_categories'], 0, 5) as $category)
                        <a href="{{ $category['href'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-base font-semibold text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                            {{ $category['name'] }}
                            <x-count-pill :count="$category['offers_count'] ?? 0" color="gray" />
                        </a>
                    @endforeach
                @endif
            </nav>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
                @if (!empty($pages))
                    <div class="order-2 min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white lg:order-1">
                        @foreach ($pages as $page)
                            <img
                                src="{{ $page['image_url'] }}"
                                alt="{{ $flyer['title'] }} – {{ $page['page_number'] }} puslapis"
                                class="block h-auto w-full"
                                loading="{{ $page['page_number'] === 1 ? 'eager' : 'lazy' }}"
                            >
                        @endforeach
                    </div>
                @elseif (!empty($flyer['image_url']))
                    <div class="order-2 min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white lg:order-1">
                        <img src="{{ $flyer['image_url'] }}" alt="{{ $flyer['title'] }}" class="block h-auto w-full">
                    </div>
                @else
                    <div class="order-2 min-w-0 rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-600 lg:order-1">
                        Leidinio puslapiai dar ruošiami.
                        <a href="/leidinys/{{ $storeSlug }}" class="font-semibold text-dark-green">Grįžti į {{ $storeName }} leidinius</a>
                    </div>
                @endif

                <aside class="order-1 min-w-0 lg:order-2">
                    <div class="sticky top-[calc(3.5rem+env(safe-area-inset-top,0px)+12px)] z-30 flex flex-col gap-3 sm:top-[calc(6.25rem+env(safe-area-inset-top,0px)+12px)]">
                        <div class="section-card">
                            <h2 class="section-heading mb-3">Kiti {{ $storeName }} leidiniai</h2>
                            @if ($otherLeaflets->isEmpty())
                                <p class="text-sm text-gray-500">Kitų leidinių nėra.</p>
                            @else
                                <div class="flex flex-col gap-2">
                                    @foreach ($otherLeaflets->take(6) as $other)
                                        <a href="{{ $other['view_url'] ?? "/leidinys/{$storeSlug}" }}" class="flex items-center gap-2.5 rounded-lg border border-gray-200 p-2 transition-colors hover:border-green hover:bg-green/5">
                                            @if (!empty($other['image_url']))
                                                <img src="{{ $other['image_url'] }}" alt="" class="h-12 w-10 shrink-0 rounded object-cover">
                                            @endif
                                            <span class="line-clamp-2 text-xs font-medium text-gray-900">{{ $other['title'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <a href="/akcijos/{{ $storeSlug }}" class="section-link">
                            Visos {{ $storeName }} akcijos
                            <x-app-icon name="chevron-right" class="size-3.5" />
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.app>
