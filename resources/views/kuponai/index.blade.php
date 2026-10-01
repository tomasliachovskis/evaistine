@php
    $orderOptions = [
        'best' => 'Populiariausi',
        'newest' => 'Naujausi',
        'old' => 'Baigsis greitai',
    ];
@endphp

<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []"
    title="Nuolaidų kodai ir kuponai"
    description="Patikrinti nuolaidų kodai ir aktualios akcijos internetinėse parduotuvėse. Rinkitės iš patikimų kuponų ir sutaupykite kiekvieną kartą apsipirkdami."
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    {{-- Ported from leaflets/index.blade.php's shell: hero -> sticky chip
         bar -> sort toggle -> card list -> "how to use" info box. --}}
    <div class="base-container mx-auto flex flex-col gap-5 pb-8 sm:gap-6 sm:pb-10">
        <div class="flex flex-col gap-2">
            <h1>Nuolaidų kodai ir aktualios nuolaidos</h1>
            <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-gray-600 sm:text-base">
                Rinkitės iš patikrintų nuolaidų kodų ir aktualių nuolaidų. Kasdien tikriname kuponus, kad galėtumėte pirkti internetu pigiau.
            </p>
            @if ($freshnessLabel)
                <p class="mt-2 text-xs text-gray-600 sm:text-sm">{{ $freshnessLabel }}</p>
            @endif
        </div>

        {{-- Big, page-level search — separate from the header's site-wide
             product search (<livewire:site-search>), scoped to just coupon
             titles/website names, since that's a distinct search intent
             from "find a product". Same rounded-full input language as
             akcijos/search-form.blade.php and stores/show.blade.php's
             search box, just sized up to be the prominent element the user
             asked for. --}}
        <form method="GET" action="/kuponai" class="relative w-full">
            @if ($order !== 'best')
                <input type="hidden" name="order" value="{{ $order }}">
            @endif
            <x-app-icon name="search" class="pointer-events-none absolute left-5 top-1/2 size-6 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                name="q"
                value="{{ $q }}"
                placeholder="Ieškoti kuponų ar parduotuvės, pvz. VidaXL, kvepalai..."
                class="w-full rounded-full border border-gray-200 bg-white py-4 pl-14 pr-5 text-lg font-medium text-gray-900 placeholder:text-gray-400 focus:border-green focus:outline-none focus:ring-1 focus:ring-green"
            >
        </form>

        @if ($q !== '')
            <p class="text-base text-gray-600">
                Paieškos „{{ $q }}“ rezultatai ({{ $coupons->total() }})
                <a href="/kuponai" class="ml-2 font-bold text-dark-green hover:underline">Išvalyti</a>
            </p>
        @endif

        @if ($websites->isNotEmpty())
            @include('kuponai.partials.website-chip-bar', ['websites' => $websites])
        @endif

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-base">
            <span class="font-bold text-gray-900">Rūšiuoti:</span>
            @foreach ($orderOptions as $value => $label)
                @php
                    $sortParams = array_filter(['order' => $value === 'best' ? null : $value, 'q' => $q !== '' ? $q : null]);
                    $sortHref = '/kuponai' . ($sortParams ? '?' . http_build_query($sortParams) : '');
                @endphp
                <a href="{{ $sortHref }}" class="inline-flex min-h-12 items-center rounded-xl px-3 {{ $order === $value ? 'bg-action font-bold text-white' : 'font-semibold text-gray-800 hover:bg-gray-100' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($coupons->isEmpty())
            <div class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-base text-gray-600">
                    @if ($q !== '')
                        Pagal „{{ $q }}“ kuponų nerasta.
                    @else
                        Šiuo metu galiojančių kuponų nėra.
                    @endif
                </p>
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($coupons as $coupon)
                    <x-coupon-card :coupon="$coupon" />
                @endforeach
            </div>

            @if ($coupons->lastPage() > 1)
                <div class="mt-2 flex justify-center">
                    {{ $coupons->onEachSide(1)->links() }}
                </div>
            @endif
        @endif

        <section id="kaip-panaudoti" class="scroll-mt-32 rounded-xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="how-to-heading">
            <h2 id="how-to-heading" class="text-base font-extrabold text-gray-900 sm:text-lg">Kaip panaudoti nuolaidų kodus?</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600 sm:text-base">
                Nuolaidos kodas – tai kuponas, kuriame yra specialus kodas. Paspaudus mygtuką „Rodyti kodą“, kodas bus parodytas ir automatiškai nukopijuotas.
                Jį reikia įrašyti į atitinkamą laukelį parduotuvės krepšelyje. Tada nuolaida bus išskaičiuota iš produktų, kuriems ji taikoma, kainos arba nuo viso pirkimo.
            </p>
        </section>
    </div>
</x-layouts.app>
