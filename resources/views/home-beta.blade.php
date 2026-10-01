<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">
    {{-- /pradzia-beta (HomeBetaController): the simpler homepage for older
         readers, under review next to "/". Three parts only: search with
         the main stores, one card per everyday keyword, one "follow
         prices" call. Mockup: https://claude.ai/artifact/EhLVNRhVj3NADCLHuJipgf --}}
    <div class="base-container mx-auto flex flex-col gap-10 pb-12 pt-6 sm:gap-14 sm:pt-12">

        {{-- Hero --}}
        {{-- Left-aligned, no eyebrow pill: the centred pill + huge headline
             + subtitle stack read as a generic template. The subtitle carries
             one concrete fact instead (today's real offer count). From lg the
             store tiles sit in a right-hand column beside the search. --}}
        <section class="flex flex-col gap-6 lg:flex-row lg:items-end lg:gap-14">
          <div class="flex min-w-0 flex-1 flex-col gap-3 sm:gap-4">
            <h1 class="m-0 text-2xl font-extrabold leading-tight tracking-tight text-dark-green sm:text-4xl">Ką šiandien perkate?</h1>
            <p class="m-0 max-w-2xl text-base leading-relaxed text-gray-600 sm:text-lg">
                @if ($totalDealsLabel)
                    Šiandien palyginome <strong class="font-bold text-gray-900">{{ $totalDealsLabel }}</strong> {{ \App\Support\LithuanianPlural::offerWordAccusative($totalDeals) }} iš {{ $storeTotal }} parduotuvių. Įrašykite prekę – parodysime, kur pigiausia.
                @else
                    Įrašykite prekę – parodysime, kur pigiausia iš {{ $storeTotal }} parduotuvių.
                @endif
            </p>
            <x-search-box size="lg" placeholder="Pienas, kava, sviestas…" class="mt-2" />
          </div>

            <div class="flex w-full flex-col gap-3 lg:w-[26rem] lg:shrink-0">
                <span class="text-base font-bold text-gray-700">Arba pasirinkite parduotuvę</span>
                <div class="flex w-full flex-wrap gap-2.5 sm:gap-3">
                    @foreach ($storeTiles as $store)
                        <a href="{{ $store['href'] }}" aria-label="{{ $store['name'] }} akcijos" class="flex h-16 w-[calc((100%-1.25rem)/3)] items-center justify-center rounded-2xl bg-white shadow-sm transition-shadow hover:shadow-md sm:h-[4.5rem] sm:w-[calc((100%-3.75rem)/6)] lg:w-[calc((100%-1.5rem)/3)]">
                            <x-store-logo :slug="$store['slug']" :name="$store['name']" size="sm" />
                        </a>
                    @endforeach
                    <a href="/parduotuves" class="flex h-16 w-[calc((100%-1.25rem)/3)] items-center justify-center rounded-2xl bg-white text-base font-bold text-dark-green shadow-sm transition-shadow hover:shadow-md sm:h-[4.5rem] sm:w-[calc((100%-3.75rem)/6)] sm:text-lg lg:w-[calc((100%-1.5rem)/3)]">+{{ $otherStoresCount }} kitos</a>
                </div>
            </div>
        </section>

        {{-- One card per keyword: 8 at first, 8 more on "Rodyti daugiau".
             flex-wrap, not CSS Grid (cards collapsed on mobile Safari in a
             grid before, see docs). --}}
        @if (count($cards))
            <section class="flex flex-col gap-4 sm:gap-5" x-data="{ more: false }">
                <div class="flex items-end justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2 class="m-0 text-xl font-extrabold tracking-tight text-gray-900 sm:text-2xl">Pigiausia šią savaitę</h2>
                        <p class="m-0 text-base text-gray-600 sm:text-lg">Geriausia kaina kiekvienai prekei</p>
                    </div>
                    <a href="/akcijos" class="hidden min-h-12 shrink-0 items-center gap-2 rounded-2xl bg-white px-5 text-base font-bold text-dark-green ring-2 ring-green-soft-border hover:bg-green-soft sm:inline-flex">
                        Visos akcijos
                        <x-app-icon name="arrow-right" class="size-5" />
                    </a>
                </div>

                <div class="flex flex-wrap gap-2 sm:gap-3">
                    @foreach ($cards as $card)
                        <x-keyword-deal-card :card="$card" :until-more="$loop->index >= 8" class="deal-card-width" />
                    @endforeach
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    @if (count($cards) > 8)
                        <button type="button" x-show="!more" @click="more = true" class="flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-action px-7 text-lg font-bold text-white hover:bg-action-hover">
                            Rodyti daugiau prekių ({{ count($cards) - 8 }})
                            <x-app-icon name="chevron-down" class="size-5" />
                        </button>
                    @endif
                    <a href="/akcijos" class="flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-white px-7 text-lg font-bold text-dark-green ring-2 ring-green-soft-border hover:bg-green-soft sm:hidden">
                        Visos akcijos
                        <x-app-icon name="arrow-right" class="size-5" />
                    </a>
                </div>
            </section>
        @endif

        {{-- The one call to action: follow prices. Guests open the shared
             sign-in sheet, signed-in readers go to their followed list. --}}
        <section x-data class="flex flex-col gap-4 rounded-3xl bg-dark-green p-6 text-white sm:flex-row sm:items-center sm:gap-7 sm:p-10">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 sm:size-[4.5rem]">
                <x-app-icon name="bell" class="size-7 fill-none text-[#ffdb4d] sm:size-9" />
            </span>
            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                <h2 class="m-0 text-xl font-extrabold tracking-tight text-white sm:text-2xl">Pranešime, kai atpigs</h2>
                <p class="m-0 text-base leading-relaxed text-white/85 sm:text-lg">Pažymėkite, ką perkate dažnai. Kai kaina nukris, parašysime el. paštu.</p>
            </div>
            @guest
                <button type="button" @click="$store.authModal.open = true" class="flex min-h-14 shrink-0 items-center justify-center rounded-2xl bg-white px-7 text-lg font-extrabold text-dark-green hover:bg-green-soft">
                    Sekti kainas nemokamai
                </button>
            @else
                <a href="/favorites" class="flex min-h-14 shrink-0 items-center justify-center rounded-2xl bg-white px-7 text-lg font-extrabold text-dark-green hover:bg-green-soft">
                    Mano stebimos prekės
                </a>
            @endguest
        </section>
    </div>
</x-layouts.app>
