@props(['facts'])

{{-- "Apie produktą" from the pharmacies' own product pages
     (App\Support\ProductFacts, docs/product-facts.md): facts about the
     product itself, never prices. Text only, no icons. --}}
<div {{ $attributes->merge(['class' => 'space-y-8 sm:space-y-6']) }}>
    @if ($facts['highlights'] !== [])
        <div>
            <h3 class="mb-3 text-base font-bold text-gray-900 sm:mb-2">Verta žinoti</h3>
            <ul class="list-disc space-y-3 pl-5 text-sm leading-relaxed text-gray-700 sm:space-y-1.5">
                @foreach ($facts['highlights'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($facts['amounts'])
        <div>
            <h3 class="mb-3 text-base font-bold text-gray-900 sm:mb-2">
                Kiekis{{ $facts['amounts']['basis'] ? ' (' . $facts['amounts']['basis'] . ')' : '' }}
            </h3>
            <table class="w-full max-w-xl text-left text-sm text-gray-700">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500">
                        <th class="py-2 pr-4 font-medium">Medžiaga</th>
                        <th class="py-2 pr-4 font-medium">Kiekis</th>
                        @if (collect($facts['amounts']['rows'])->contains(fn ($row) => $row['rmv'] !== null))
                            <th class="py-2 font-medium">RMV*</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($facts['amounts']['rows'] as $row)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 pr-4 sm:py-2">{{ $row['name'] }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap sm:py-2">{{ $row['amount'] }}</td>
                            @if (collect($facts['amounts']['rows'])->contains(fn ($r) => $r['rmv'] !== null))
                                <td class="py-3 whitespace-nowrap sm:py-2">{{ $row['rmv'] ?? '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (collect($facts['amounts']['rows'])->contains(fn ($row) => $row['rmv'] !== null))
                <p class="mt-2 text-xs text-gray-500">* RMV – referencinė maistinė vertė.</p>
            @endif
        </div>
    @endif

    @if (!empty($facts['ingredients']))
        <div>
            <h3 class="mb-3 text-base font-bold text-gray-900 sm:mb-2">Sudėtis</h3>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ $facts['ingredients'] }}</p>
        </div>
    @endif

    @if ($facts['same_substance'])
        <div>
            <h3 class="mb-3 text-base font-bold text-gray-900 sm:mb-2">{{ $facts['same_substance']['label'] }}</h3>
            <ul class="space-y-3 text-sm sm:space-y-1.5">
                @foreach ($facts['same_substance']['products'] as $item)
                    <li><a href="{{ $item['url'] }}" class="text-dark-green underline-offset-2 hover:underline">{{ $item['name'] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-1 text-xs text-gray-500">
        <p>Šaltinis – prekės aprašymas el. vaistinėse: {{ implode(', ', $facts['sources']) }}.</p>
        <p>Prieš vartodami perskaitykite informaciją ant pakuotės.</p>
    </div>
</div>
