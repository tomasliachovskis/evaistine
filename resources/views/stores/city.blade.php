<x-layouts.app
    :title="$title"
    :description="$description"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-6 pb-8 sm:pb-10">
        <div>
            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $store->name }} {{ $cityName }}</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ $locations->count() }} {{ $locations->count() === 1 ? 'parduotuvė' : 'parduotuvės' }} {{ $cityName }} mieste — adresai, darbo laikas ir kontaktai.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="flex flex-col gap-3">
                @foreach ($locations as $location)
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <p class="font-semibold text-gray-900">{{ $location['address'] }}</p>
                        @if (!empty($location['phone']))
                            <p class="mt-1 text-sm text-gray-600">
                                <a href="tel:{{ $location['phone'] }}" class="hover:text-green">{{ $location['phone'] }}</a>
                            </p>
                        @endif
                        @if (!empty($location['hours']))
                            <details class="mt-2 text-sm">
                                <summary class="cursor-pointer font-medium text-gray-500 hover:text-gray-700">
                                    Šiandien ({{ $dayLabels[$todayKey] }}): {{ $location['hours'][$todayKey] ?? 'Nedirba' }}
                                </summary>
                                <dl class="mt-2 flex flex-col gap-0.5">
                                    @foreach ($dayLabels as $day => $label)
                                        <div class="flex justify-between gap-2 {{ $day === $todayKey ? 'font-semibold text-gray-900' : '' }}">
                                            <dt class="text-gray-500">{{ $label }}</dt>
                                            <dd class="font-medium text-gray-900">{{ $location['hours'][$day] ?? 'Nedirba' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </details>
                        @endif
                    </div>
                @endforeach

                <a
                    href="/akcijos/{{ $store->slug }}"
                    class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-900 hover:border-green hover:text-green"
                >
                    Žiūrėti {{ $store->name }} akcijas
                </a>
            </div>

            {{-- isolate: Leaflet's own panes/controls/popups use z-index up
                 to 1000 internally, which bleeds through the site's fixed
                 header (z-50) once scrolled without a new stacking context
                 here. --}}
            <div class="isolate h-[400px] lg:h-auto">
                <div
                    x-data="storeLocatorMap({{ $locations->map(fn ($l) => ['lat' => $l['lat'], 'lng' => $l['lng'], 'address' => $l['address'], 'city' => $l['city']])->values()->toJson() }})"
                    class="h-full min-h-[400px] w-full rounded-xl border border-gray-200"
                ></div>
            </div>
        </div>
    </div>
</x-layouts.app>
