@php
    $locationsByCity = $locations->groupBy('city');
    $cityLabel = fn ($count) => match (true) {
        $count === 1 => 'parduotuvė',
        $count % 10 >= 2 && $count % 10 <= 9 && !($count % 100 >= 11 && $count % 100 <= 19) => 'parduotuvės',
        default => 'parduotuvių',
    };
    $dayLabels = ['monday' => 'Pirmadienis', 'tuesday' => 'Antradienis', 'wednesday' => 'Trečiadienis',
        'thursday' => 'Ketvirtadienis', 'friday' => 'Penktadienis', 'saturday' => 'Šeštadienis', 'sunday' => 'Sekmadienis'];
@endphp

<x-layouts.app
    :title="$store->name . ($citySlug ? ' parduotuvės' : ' parduotuvių tinklas') . ' – adresai ir darbo laikas'"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-6 pb-8 sm:pb-10">
        <div>
            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $store->name }} parduotuvės ir darbo laikas</h1>
            <p class="mt-1 text-sm text-gray-600">
                Iš viso {{ $locations->count() }} {{ $cityLabel($locations->count()) }} Lietuvoje.
                Suraskite artimiausią {{ $store->name }} parduotuvę pagal miestą arba adresą.
            </p>
        </div>

        @if ($locations->isEmpty())
            <div class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-sm text-gray-600">Šiuo metu parduotuvių sąrašo nėra.</p>
            </div>
        @else
            <div class="flex flex-col gap-4" x-data="{ query: '' }">
                <input
                    type="text"
                    x-model="query"
                    placeholder="Įveskite miestą arba adresą..."
                    aria-label="Ieškoti parduotuvės pagal miestą arba adresą"
                    class="w-full max-w-md rounded-lg border border-gray-300 px-3.5 py-2 text-sm focus:border-green focus:outline-none focus:ring-1 focus:ring-green"
                >

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="order-2 flex max-h-[600px] flex-col gap-3 overflow-y-auto pr-1 lg:order-1">
                        @foreach ($locationsByCity as $city => $cityLocations)
                            <template x-if="!query.trim() || '{{ Str::lower($city) }}'.includes(query.trim().toLowerCase())">
                                <div>
                                    <h2 class="mb-2 font-semibold text-gray-900">{{ $city }}</h2>
                                    <div class="flex flex-col gap-3">
                                        @foreach ($cityLocations as $location)
                                            <div
                                                x-show="!query.trim() || '{{ Str::lower($city.' '.$location['address']) }}'.includes(query.trim().toLowerCase())"
                                                class="rounded-xl border border-gray-200 bg-white p-4"
                                            >
                                                <p class="font-semibold text-gray-900">{{ $location['address'] }}</p>
                                                @if (!empty($location['phone']))
                                                    <p class="mt-1 text-sm text-gray-600">{{ $location['phone'] }}</p>
                                                @endif
                                                @if (!empty($location['hours']))
                                                    <details class="mt-2 text-sm">
                                                        <summary class="cursor-pointer font-medium text-gray-500 hover:text-gray-700">Visa savaitė</summary>
                                                        <dl class="mt-2 flex flex-col gap-0.5">
                                                            @foreach ($dayLabels as $day => $label)
                                                                <div class="flex justify-between gap-2">
                                                                    <dt class="text-gray-500">{{ $label }}</dt>
                                                                    <dd class="font-medium text-gray-900">{{ $location['hours'][$day] ?? 'Nedirba' }}</dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </details>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </template>
                        @endforeach
                    </div>

                    <div class="order-1 h-[400px] lg:order-2 lg:h-auto">
                        <div
                            x-data="storeLocatorMap({{ $locations->map(fn ($l) => ['lat' => $l['lat'], 'lng' => $l['lng'], 'address' => $l['address'], 'city' => $l['city']])->values()->toJson() }})"
                            class="h-full min-h-[400px] w-full rounded-xl border border-gray-200"
                        ></div>
                    </div>
                </div>
            </div>
        @endif

        @if (!$citySlug && $cities->count() > 1)
            <div>
                <h2 class="mb-2.5 text-base font-bold text-gray-900">{{ $store->name }} pagal miestus</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($cities as $city)
                        <a href="/parduotuves/{{ $store->slug }}/{{ \Illuminate\Support\Str::slug($city) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                            {{ $city }}
                            <span class="text-xs text-gray-400">({{ $locationsByCity[$city]->count() }})</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
