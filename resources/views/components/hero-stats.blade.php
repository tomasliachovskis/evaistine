@props(['stats' => [], 'freshness' => null])

{{-- Compact fact row under a page's H1: one pill per stat ($stats: list of
     ['icon' => app-icon name, 'pill' => text, 'mobile_first' => bool]) plus
     an unboxed "Atnaujinta" item. ~30px tall at every width so products stay
     near the top of the screen — scrolls sideways on a phone instead of
     wrapping, wraps on sm+. With no stats it's just the freshness line. --}}
@php
    // mobile_first stats (price, max discount) lead: the facts that decide
    // whether to keep scrolling.
    $orderedStats = collect($stats)
        ->filter(fn ($stat) => !empty($stat['pill']))
        ->sortBy(fn ($stat) => empty($stat['mobile_first']) ? 1 : 0)
        ->values();
@endphp

@if ($orderedStats->isNotEmpty() || $freshness)
    <div {{ $attributes }}>
        @if ($orderedStats->isNotEmpty())
            <ul class="-mx-4 flex gap-1.5 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:gap-2 sm:overflow-visible sm:px-0 [&::-webkit-scrollbar]:hidden">
                @foreach ($orderedStats as $stat)
                    <li class="flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-800 sm:px-3 sm:text-sm">
                        <x-app-icon :name="$stat['icon'] ?? 'tag'" class="size-3.5 text-dark-green sm:size-4" />
                        {{ $stat['pill'] }}
                    </li>
                @endforeach
                @if ($freshness)
                    {{-- Same type as the pills, no chip: on sm+ it sits at the
                         end of the row. --}}
                    <li class="hidden shrink-0 items-center gap-1.5 whitespace-nowrap px-1 py-1 text-sm font-semibold text-gray-500 sm:flex">
                        <x-app-icon name="clock" class="size-4" />
                        {{ $freshness }}
                    </li>
                @endif
            </ul>
        @endif
        @if ($freshness)
            {{-- Own line on mobile (the end of the sideways-scrolling row
                 would be off-screen), and on every width when there are no
                 pills to sit next to. --}}
            <p @class([
                'flex items-center gap-1.5 text-xs font-semibold text-gray-500',
                'mt-1.5 sm:hidden' => $orderedStats->isNotEmpty(),
                'sm:text-sm' => $orderedStats->isEmpty(),
            ])>
                <x-app-icon name="clock" class="size-3.5 sm:size-4" />
                {{ $freshness }}
            </p>
        @endif
    </div>
@endif
