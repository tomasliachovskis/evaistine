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

            <x-store-nav-tabs
                :store-slug="$storeSlug"
                :leaflets-count="$listingMeta['leaflets_count'] ?? 0"
                :total-offers="$totalOffers"
                :categories="$listingMeta['top_categories'] ?? []"
                :aria-label="$storeName . ' skiltys'"
                active="leidiniai"
            />

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
