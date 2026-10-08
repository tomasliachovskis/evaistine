@props(['categorySlug' => null])

{{-- The warning a medicine or food supplement carries, shown wherever such a
     product is listed. The medicine text is the one the advertising rules
     require for non-prescription medicines shown to the public; we show it
     even though a price list isn't advertising (docs/evaistine.md, "Legal").
     Renders nothing for other categories. --}}
@php
    $notices = [
        'nereceptiniai-vaistai' => [
            'title' => 'Vaistinis preparatas.',
            'text' => 'Prašome įdėmiai perskaityti pakuotės lapelį ir vaistą vartoti kaip nurodyta. Netinkamai vartojamas vaistas gali pakenkti Jūsų sveikatai. Dėl vartojimo pasitarkite su gydytoju ar vaistininku.',
        ],
        'vitaminai-ir-maisto-papildai' => [
            'title' => 'Maisto papildas.',
            'text' => 'Maisto papildas nepakeičia visavertės ir įvairios mitybos. Nevartokite daugiau nei rekomenduojama paros dozė.',
        ],
    ];
    $notice = $notices[$categorySlug] ?? null;
@endphp

@if ($notice)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 rounded-lg bg-gray-50 px-3 py-3 sm:px-4']) }}>
        <x-app-icon name="info" class="mt-0.5 size-5 shrink-0 text-gray-500" />
        <p class="text-sm leading-snug text-gray-700">
            <strong class="font-semibold text-gray-900">{{ $notice['title'] }}</strong>
            {{ $notice['text'] }}
        </p>
    </div>
@endif
