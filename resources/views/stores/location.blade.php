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
            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $store->name }} {{ $location->address }}, {{ $location->city }}</h1>
            <p class="mt-1 text-sm text-gray-600">Darbo laikas ir kontaktai</p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="flex flex-col gap-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="font-semibold text-gray-900">Adresas</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $location->address }}, {{ $location->city }}</p>

                    @if (!empty($location->phone))
                        <p class="mt-3 font-semibold text-gray-900">Telefonas</p>
                        <p class="mt-1 text-sm text-gray-600">
                            <a href="tel:{{ $location->phone }}" class="hover:text-green">{{ $location->phone }}</a>
                        </p>
                    @endif
                </div>

                @if (!empty($location->hours))
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <p class="font-semibold text-gray-900">Darbo laikas</p>
                        <dl class="mt-2 flex flex-col gap-0.5">
                            @foreach ($dayLabels as $day => $label)
                                <div class="flex justify-between gap-2 text-sm {{ $day === $todayKey ? 'font-semibold text-gray-900' : 'text-gray-600' }}">
                                    <dt>{{ $label }}{{ $day === $todayKey ? ' (šiandien)' : '' }}</dt>
                                    <dd>{{ $location->hours[$day] ?? 'Nedirba' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <a
                    href="/akcijos/{{ $store->slug }}"
                    class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-900 hover:border-green hover:text-green"
                >
                    Žiūrėti {{ $store->name }} akcijas
                </a>
            </div>

            <div class="h-[300px] lg:h-auto">
                <div
                    x-data="storeLocatorMap([{ lat: {{ $location->lat }}, lng: {{ $location->lng }}, address: '{{ addslashes($location->address) }}', city: '{{ addslashes($location->city) }}' }])"
                    class="h-full min-h-[300px] w-full rounded-xl border border-gray-200"
                ></div>
            </div>
        </div>
    </div>
</x-layouts.app>
