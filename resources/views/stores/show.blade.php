@php
    $cityLabel = fn ($count) => match (true) {
        $count === 1 => 'parduotuvė',
        $count % 10 >= 2 && $count % 10 <= 9 && !($count % 100 >= 11 && $count % 100 <= 19) => 'parduotuvės',
        default => 'parduotuvių',
    };
@endphp

<x-layouts.app
    :title="$store->name . ' parduotuvių tinklas – adresai ir darbo laikas'"
    :description="'Raskite artimiausią ' . $store->name . ' parduotuvę — visi ' . $totalCount . ' adresai, darbo laikas ir kontaktai vienoje vietoje.'"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-6 pb-8 sm:pb-10">
        <div>
            <h1>{{ $store->name }} parduotuvės ir darbo laikas</h1>
            <p class="mt-1 text-sm text-gray-600">
                Iš viso {{ $totalCount }} {{ $cityLabel($totalCount) }} Lietuvoje.
                Pasirinkite miestą, kad pamatytumėte visus adresus ir darbo laiką.
            </p>
        </div>

        @if (!$cities->isEmpty())
            {{-- "Netoliese manęs" / nearest-store finder — real geolocation +
                 distance against every location's own lat/lng, not just a
                 "near me" label with nothing behind it. Locations come from
                 the API (shared with the map below), not inlined here: the
                 full address list in this page's HTML made Google treat
                 every /parduotuves/{store}/{city} page as a duplicate of
                 this one. --}}
            <div
                x-data="nearestStoreFinder(@js($locationsUrl))"
                class="rounded-xl border border-gray-200 bg-white p-4"
            >
                <template x-if="!result && !loading && !error">
                    <button
                        type="button"
                        @click="find()"
                        class="inline-flex items-center gap-2 text-sm font-medium text-dark-green hover:underline"
                    >
                        <x-app-icon name="map-pin" class="size-4" />
                        Rasti artimiausią {{ mb_strtolower($store->name) }} parduotuvę
                    </button>
                </template>
                <p x-show="loading" class="text-sm text-gray-600">Nustatoma jūsų vieta...</p>
                <p x-show="error" x-text="error" class="text-sm text-gray-600"></p>
                <template x-if="result">
                    <p class="text-sm text-gray-900">
                        Artimiausia parduotuvė:
                        <a :href="'/parduotuves/{{ $store->slug }}/' + result.citySlug" class="font-semibold text-dark-green hover:underline" x-text="result.address + ', ' + result.city"></a>
                        <span class="text-gray-600" x-text="'(~' + result.distanceKm + ' km)'"></span>
                    </p>
                </template>
            </div>
        @endif

        @if ($cities->isEmpty())
            <div class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-sm text-gray-600">Šiuo metu parduotuvių sąrašo nėra.</p>
            </div>
        @else
            <div class="flex flex-col gap-4" x-data="{ query: '' }">
                <input
                    type="text"
                    x-model="query"
                    placeholder="Įveskite miestą..."
                    aria-label="Ieškoti pagal miestą"
                    class="w-full max-w-md rounded-lg border border-gray-300 px-3.5 py-2 text-sm focus:border-green focus:outline-none focus:ring-1 focus:ring-green"
                >

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="order-2 grid max-h-[600px] grid-cols-1 gap-3 overflow-y-auto pr-1 sm:grid-cols-2 lg:order-1">
                        @foreach ($cities as $city)
                            <a
                                href="/parduotuves/{{ $store->slug }}/{{ $city['slug'] }}"
                                x-show="!query.trim() || '{{ Str::lower($city['name']) }}'.includes(query.trim().toLowerCase())"
                                class="rounded-xl border border-gray-200 bg-white p-4 hover:border-green"
                            >
                                <p class="font-semibold text-gray-900">{{ $city['name'] }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $city['count'] }} {{ $cityLabel($city['count']) }}</p>
                                @if ($city['sampleAddress'])
                                    <p class="mt-1 truncate text-xs text-gray-400">{{ $city['sampleAddress'] }}{{ $city['count'] > 1 ? ' ir kt.' : '' }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    {{-- isolate: Leaflet's own panes/controls/popups use
                         z-index up to 1000 internally, which without a new
                         stacking context here bleeds straight through the
                         site's fixed header (z-50) once scrolled — this
                         caps everything inside to this box, however high
                         Leaflet sets it. --}}
                    <div class="order-1 isolate h-[400px] lg:order-2 lg:h-auto">
                        <div
                            x-data="storeLocatorMap(@js($locationsUrl))"
                            class="h-full min-h-[400px] w-full rounded-xl border border-gray-200"
                        ></div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
