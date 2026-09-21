@php
    $orderOptions = [
        'best' => 'Populiariausi',
        'newest' => 'Naujausi',
        'old' => 'Baigsis greitai',
    ];
@endphp

<x-layouts.app
    title="{{ $website->name }} nuolaidų kodai ir kuponai"
    description="Patikrinti {{ $website->name }} nuolaidų kodai ir aktualios akcijos. Sutaupykite naudodami galiojančius kuponus."
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    {{-- Ported from leaflets/hub.blade.php's shell: hero -> quick-links
         pill bar -> card list -> "how to use" info box. --}}
    <div class="base-container mx-auto flex w-full flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <x-type-hero :icon-src="$website->logo_url" :title="$website->name . ' nuolaidų kodai ir kuponai'" :subtitle="$website->description" />

        {{-- Same visual language as <x-leaflet-quick-links> (copied class
             strings — that component is worded around leidinys/store, not
             reusable verbatim for a coupon website). --}}
        <div class="flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-2xl border border-gray-300 bg-[#e8e8e8] px-4 py-3 min-h-[60px] sm:min-h-[52px] sm:px-[20px]">
            <a href="/kuponai" class="inline-flex h-full shrink-0 items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]">
                <x-app-icon name="arrow-right" class="size-5 shrink-0 rotate-180" />
                <span>Visi kuponai</span>
            </a>
            @if ($website->url)
                <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-full shrink-0 items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]">
                    <x-app-icon name="tag" class="size-5 shrink-0" />
                    <span>{{ $website->name }} svetainė</span>
                </a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-base">
            <span class="font-bold text-gray-900">Rūšiuoti:</span>
            @foreach ($orderOptions as $value => $label)
                @php $href = "/kuponai/{$website->slug}" . ($value === 'best' ? '' : "?order={$value}"); @endphp
                <a href="{{ $href }}" class="{{ $order === $value ? 'font-bold text-green' : 'font-medium text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($coupons->isEmpty())
            <div class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-base text-gray-600">Šiuo metu galiojančių {{ $website->name }} kuponų nėra — užsukite vėliau.</p>
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($coupons as $coupon)
                    <x-coupon-card :coupon="$coupon" :show-website-link="false" />
                @endforeach
            </div>

            @if ($coupons->lastPage() > 1)
                <div class="mt-2 flex justify-center">
                    {{ $coupons->onEachSide(1)->links() }}
                </div>
            @endif
        @endif

        <section id="kaip-panaudoti" class="scroll-mt-24 rounded-xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="how-to-heading">
            <h2 id="how-to-heading" class="text-base font-extrabold text-gray-900 sm:text-lg">Kaip panaudoti {{ $website->name }} nuolaidos kodą?</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600 sm:text-base">
                Paspaudus mygtuką „Rodyti kodą“, kodas bus parodytas ir automatiškai nukopijuotas į iškarpinę.
                Jį reikia įrašyti į atitinkamą laukelį {{ $website->name }} krepšelyje apsiperkant
                @if ($website->url)
                    <a href="{{ $website->url }}" target="_blank" rel="noopener noreferrer" class="font-bold text-green hover:text-dark-green">{{ $website->url }}</a>.
                @else
                    svetainėje.
                @endif
            </p>
        </section>
    </div>
</x-layouts.app>
