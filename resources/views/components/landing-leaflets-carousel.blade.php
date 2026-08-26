@props(['leaflets', 'title' => 'Naujausi akcijų leidiniai', 'seeAllHref' => '/leidiniai'])

{{-- Same header + snap-scroll carousel pattern as <x-landing-deals-section>,
     but leaflet-card's portrait aspect ratio and its own grid sizing (see
     leaflets/index.blade.php) need different item widths than deal-card's,
     so this isn't just a variant of that component. --}}
@if (count($leaflets))
    <section aria-labelledby="leaflets-carousel-heading" class="min-w-0 p-2 max-sm:-mx-4 max-sm:px-4 sm:p-3">
        <div class="mb-2.5 flex items-center justify-between gap-2 sm:mb-3">
            <div class="flex items-center gap-1.5 min-w-0">
                <x-app-icon name="newspaper" class="size-4 shrink-0 text-green sm:hidden" />
                <h2 id="leaflets-carousel-heading" class="text-[0.95rem] font-semibold leading-tight text-gray-900 sm:text-2xl sm:font-extrabold sm:leading-snug">
                    {{ $title }}
                </h2>
            </div>
            @if ($seeAllHref)
                <a href="{{ $seeAllHref }}" class="inline-flex shrink-0 items-center gap-0.5 text-sm font-medium text-green hover:text-dark-green sm:font-semibold">
                    Žiūrėti visus
                    <x-app-icon name="chevron-right" class="size-3.5 opacity-80" />
                </a>
            @endif
        </div>

        <div class="scroll-cards-x -mx-0.5 flex w-full min-w-0 snap-x snap-mandatory items-stretch gap-1.5 px-0.5 sm:mx-0 sm:gap-3 sm:px-0">
            @foreach ($leaflets as $leaflet)
                <div class="w-[calc((100%-0.375rem)/2.3)] min-w-[152px] max-w-[220px] shrink-0 grow-0 basis-[calc((100%-0.375rem)/2.3)] snap-start sm:w-[220px] sm:min-w-[220px] sm:max-w-none sm:basis-auto">
                    <x-leaflet-card :leaflet="$leaflet" class="h-full" />
                </div>
            @endforeach
        </div>
    </section>
@endif
