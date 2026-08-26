@props(['items', 'layout' => 'grid'])

{{-- Ported from listing-faq.tsx's faqGrid — shared by the product page,
     category/store listing pages, and the leaflet hub page so all three
     look identical (collapsible bordered rows, first one open by default)
     instead of each page reinventing its own FAQ markup. --}}
@if (count($items))
    <div class="grid grid-cols-1 items-start gap-2 {{ $layout === 'grid' ? 'md:grid-cols-2' : '' }}" x-data="{ open: 0 }">
        @foreach ($items as $index => $item)
            <div class="self-start overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                <button type="button" @click="open = open === {{ $index }} ? null : {{ $index }}" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-medium transition-colors hover:bg-white" :aria-expanded="open === {{ $index }}">
                    <span class="leading-tight">{{ $item['question'] }}</span>
                    <span :class="open === {{ $index }} && 'rotate-180'" class="shrink-0 transition-transform">
                        <x-app-icon name="chevron-down" class="size-5 text-gray-500" />
                    </span>
                </button>
                <div x-show="open === {{ $index }}" x-cloak class="border-t border-gray-200 bg-white px-4 py-3 text-sm leading-snug text-gray-600 [&_a]:font-medium [&_a]:text-green [&_a]:hover:text-dark-green [&_a]:hover:underline">
                    {!! $item['answer'] !!}
                </div>
            </div>
        @endforeach
    </div>
@endif
