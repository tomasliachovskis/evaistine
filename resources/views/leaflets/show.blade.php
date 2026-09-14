@php
    $flyer = $listingMeta['flyer'];
    $pages = $listingMeta['pages'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $otherLeaflets = collect($listingMeta['leaflets'] ?? [])->filter(fn ($l) => ($l['slug'] ?? null) !== $flyer['slug'])->values();
    // Y.m.d (dots), not Y-m-d — matches every listing card's date range
    // format (leaflets/hub.blade.php, components/leaflet-card.blade.php).
    $dateRange = ($flyer['valid_from'] ?? null) && ($flyer['valid_to'] ?? null)
        ? \Illuminate\Support\Carbon::parse($flyer['valid_from'])->format('Y.m.d') . ' – ' . \Illuminate\Support\Carbon::parse($flyer['valid_to'])->format('Y.m.d')
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
            <x-type-hero
                :icon-src="'/assets/stores/' . $storeSlug . '.svg'"
                :title="$flyer['title']"
                :subtitle="$dateRange"
            >
                <x-slot:cta>
                    {{-- The raw PDF is scrape/OCR source material, never a user-facing
                         download — production shows a "Sekti akcijas" follow CTA here
                         instead, never exposing the PDF URL at all. --}}
                    <x-store-subscribe-button />
                </x-slot:cta>
            </x-type-hero>

            <x-store-nav-tabs
                :store-slug="$storeSlug"
                :leaflets-count="$listingMeta['leaflets_count'] ?? 0"
                :total-offers="$totalOffers"
                :aria-label="$storeName . ' skiltys'"
                active="leidiniai"
            />

            <div class="grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
                @if (!empty($pages))
                    @php $lastPage = count($pages); @endphp
                    <div class="order-1 min-w-0" x-data="{ currentPage: 1, totalPages: {{ $lastPage }}, fullscreen: false }" @keydown.escape.window="fullscreen = false">
                        {{-- Real gap found benchmarking manoakcijos.lt: this used to
                             stack every scanned page into one long vertical strip —
                             unusable for a 60-70 page leaflet (no way to jump to a
                             page except scrolling). Numbered pager + prev/next +
                             fullscreen instead, same page images already loaded. --}}
                        <div class="mb-3 flex flex-wrap items-center gap-1.5">
                            @foreach ($pages as $page)
                                @continue($lastPage > 6 && $page['page_number'] > 5 && $page['page_number'] !== $lastPage)
                                @if ($lastPage > 6 && $page['page_number'] === $lastPage)
                                    <span class="px-1 text-sm font-bold text-gray-400">…</span>
                                @endif
                                <button
                                    type="button"
                                    @click="currentPage = {{ $page['page_number'] }}"
                                    :class="currentPage === {{ $page['page_number'] }} ? 'border-green bg-green text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-green/40'"
                                    class="flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-2 text-sm font-bold transition-colors"
                                >{{ $page['page_number'] }}</button>
                            @endforeach
                        </div>

                        <div
                            class="relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                            :class="fullscreen && 'fixed inset-0 z-[9999] flex items-center justify-center rounded-none border-none bg-black/95 p-4'"
                        >
                            @foreach ($pages as $page)
                                <img
                                    x-show="currentPage === {{ $page['page_number'] }}"
                                    x-cloak
                                    src="{{ $page['image_url'] }}"
                                    alt="{{ $flyer['title'] }} – {{ $page['page_number'] }} puslapis"
                                    class="block h-auto w-full"
                                    :class="fullscreen && 'mx-auto h-auto max-h-full w-auto max-w-full object-contain'"
                                    loading="{{ $page['page_number'] <= 2 ? 'eager' : 'lazy' }}"
                                >
                            @endforeach

                            <button
                                type="button"
                                @click="currentPage = Math.max(1, currentPage - 1)"
                                x-show="currentPage > 1"
                                aria-label="Ankstesnis puslapis"
                                class="absolute left-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                            >
                                <x-app-icon name="chevron-right" class="size-4 rotate-180" />
                            </button>
                            <button
                                type="button"
                                @click="currentPage = Math.min(totalPages, currentPage + 1)"
                                x-show="currentPage < totalPages"
                                aria-label="Kitas puslapis"
                                class="absolute right-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                            >
                                <x-app-icon name="chevron-right" class="size-4" />
                            </button>
                            <button
                                type="button"
                                @click="fullscreen = !fullscreen"
                                :aria-label="fullscreen ? 'Uždaryti pilną ekraną' : 'Pilnas ekranas'"
                                class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                            >
                                <x-app-icon x-show="!fullscreen" name="maximize" class="size-4" />
                                <x-app-icon x-show="fullscreen" name="x" class="size-4" />
                            </button>
                        </div>
                    </div>
                @elseif (!empty($flyer['image_url']))
                    <div class="order-1 min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white lg:order-1">
                        <img src="{{ $flyer['image_url'] }}" alt="{{ $flyer['title'] }}" class="block h-auto w-full">
                    </div>
                @else
                    <div class="order-1 min-w-0 rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-600 lg:order-1">
                        Leidinio puslapiai dar ruošiami.
                        <a href="/leidinys/{{ $storeSlug }}" class="font-semibold text-dark-green">Grįžti į {{ $storeName }} leidinius</a>
                    </div>
                @endif

                <aside class="order-2 min-w-0 lg:order-2">
                    <div class="flex flex-col gap-3 lg:sticky lg:top-[calc(6.25rem+env(safe-area-inset-top,0px)+12px)]">
                        <div class="section-card">
                            <h2 class="section-heading mb-3">Kiti {{ $storeName }} leidiniai</h2>
                            @if ($otherLeaflets->isEmpty())
                                <p class="text-sm text-gray-500">Kitų leidinių nėra.</p>
                            @else
                                <div class="flex flex-col gap-2">
                                    @foreach ($otherLeaflets->take(6) as $other)
                                        <a href="{{ $other['view_url'] ?? "/leidinys/{$storeSlug}" }}" class="flex items-center gap-3 rounded-lg border border-gray-200 p-2 transition-colors hover:border-green hover:bg-green/5">
                                            @if (!empty($other['image_url']))
                                                <img src="{{ $other['image_url'] }}" alt="" class="h-20 w-16 shrink-0 rounded-md object-cover">
                                            @endif
                                            <span class="line-clamp-2 text-sm font-medium text-gray-900">{{ $other['title'] }}</span>
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
