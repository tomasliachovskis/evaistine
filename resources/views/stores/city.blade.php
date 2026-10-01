<x-layouts.app
    :title="$title"
    :description="$description"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($locationsSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-6 pb-8 sm:pb-10">
        <div>
            <h1>{{ $store->name }} {{ $cityName }}</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ $locations->count() }} {{ \App\Support\LithuanianPlural::storeWord($locations->count()) }} {{ $cityName }} mieste — adresai, darbo laikas ir kontaktai.
            </p>
        </div>

        @if ($hoursFacts)
            <section class="rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="text-base font-semibold text-gray-900">{{ $store->name }} darbo laikas {{ $cityName }}</h2>
                <ul class="mt-2 flex flex-col gap-1 text-sm text-gray-600">
                    @foreach ($hoursFacts as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($offersCount > 0 || $leafletsCount > 0)
            <div class="flex flex-wrap gap-2">
                @if ($offersCount > 0)
                    <a
                        href="/akcijos/{{ $store->slug }}"
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-900 hover:border-green hover:text-green"
                    >
                        <x-app-icon name="tag" class="size-4" />
                        Žiūrėti {{ $store->name }} akcijas ({{ number_format($offersCount, 0, ',', ' ') }})
                    </a>
                @endif
                @if ($leafletsCount > 0)
                    <a
                        href="/leidinys/{{ $store->slug }}"
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-900 hover:border-green hover:text-green"
                    >
                        <x-app-icon name="bookmark" class="size-4" />
                        Žiūrėti {{ $store->name }} {{ $store->slug === 'iki' ? 'leidynius' : 'leidinius' }} ({{ $leafletsCount }})
                    </a>
                @endif
            </div>
        @endif

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
                        @if ($location['hours_summary'])
                            <p class="mt-2 flex items-start gap-1.5 text-sm text-gray-600">
                                <x-app-icon name="clock" class="mt-0.5 size-4 shrink-0 text-gray-400" />
                                <span>{{ $location['hours_summary'] }}</span>
                            </p>
                        @endif
                    </div>
                @endforeach

            </div>

            {{-- isolate: Leaflet's own panes/controls/popups use z-index up
                 to 1000 internally, which bleeds through the site's fixed
                 header (z-50) once scrolled without a new stacking context
                 here. Fixed height + sticky on desktop: stretched to the full
                 address column (60 cards for Vilnius), Leaflet centered the
                 pins in a column taller than the screen, so the visible
                 part showed empty map or a neighbouring country. --}}
            <div class="isolate h-[400px] lg:sticky lg:top-28 lg:h-[min(600px,calc(100vh-8.5rem))] lg:self-start">
                <div
                    x-data="storeLocatorMap({{ $locations->map(fn ($l) => ['lat' => $l['lat'], 'lng' => $l['lng'], 'address' => $l['address'], 'city' => $l['city']])->values()->toJson() }})"
                    class="h-full min-h-[400px] w-full rounded-xl border border-gray-200"
                ></div>
            </div>
        </div>
    </div>
</x-layouts.app>
