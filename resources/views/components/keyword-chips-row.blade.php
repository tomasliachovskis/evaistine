@props(['pages', 'ariaLabel' => 'Susiję pasiūlymai', 'title' => null])

{{-- Related keyword-page links (e.g. "Makaronai", "Aliejus") as a single
     horizontally-scrollable row — explicit product decision: one line,
     scrollable, instead of wrap-to-2-rows + "Rodyti daugiau". Kept lighter
     than the hero stat pills above it (smaller chips, count as plain text),
     with an edge fade + desktop arrows so it reads as scrollable instead of
     just cut off. --}}
@if (!empty($pages))
    <div
        x-data="{
            canLeft: false,
            canRight: false,
            update() {
                const track = $refs.track;
                this.canLeft = track.scrollLeft > 4;
                this.canRight = track.scrollLeft + track.clientWidth < track.scrollWidth - 4;
            },
            go(direction) {
                $refs.track.scrollBy({ left: direction * $refs.track.clientWidth * 0.8, behavior: 'smooth' });
            },
        }"
        x-init="$nextTick(() => update())"
        @resize.window.debounce.100ms="update()"
    >
        @if ($title)
            <p class="mb-2 text-sm font-semibold text-gray-700">{{ $title }}</p>
        @endif
        <div class="relative -mx-4 sm:mx-0">
            <nav
                x-ref="track"
                @scroll.debounce.50ms="update()"
                aria-label="{{ $title ?? $ariaLabel }}"
                class="flex flex-nowrap items-center gap-2 overflow-x-auto px-4 py-0.5 [scrollbar-width:none] sm:px-0 [&::-webkit-scrollbar]:hidden"
            >
                @foreach ($pages as $page)
                    <a href="{{ $page['href'] }}" class="inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-gray-200 bg-white px-3.5 text-sm font-semibold text-gray-700 transition-colors hover:border-green/40 hover:text-dark-green">
                        @if (!empty($page['logo_slug']))
                            <x-store-logo :slug="$page['logo_slug']" :name="$page['title']" size="xs" />
                        @endif
                        {{ $page['title'] }}
                        @if (!empty($page['matching_offers_count']))
                            <span class="text-xs font-medium tabular-nums text-gray-400">{{ number_format($page['matching_offers_count'], 0, ',', ' ') }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            {{-- Edge fades: only while there's more to scroll that way. --}}
            <div x-show="canLeft" x-transition.opacity x-cloak class="pointer-events-none absolute inset-y-0 left-0 w-12 bg-gradient-to-r from-background to-transparent"></div>
            <div x-show="canRight" x-transition.opacity x-cloak class="pointer-events-none absolute inset-y-0 right-0 w-16 bg-gradient-to-l from-background to-transparent"></div>

            {{-- Desktop arrows (touch scrolls natively on mobile). --}}
            <button type="button" x-show="canLeft" x-cloak @click="go(-1)" aria-label="Slinkti atgal" class="absolute left-0 top-1/2 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:text-dark-green sm:flex">
                <x-app-icon name="chevron-right" class="size-4 rotate-180" />
            </button>
            <button type="button" x-show="canRight" x-cloak @click="go(1)" aria-label="Slinkti toliau" class="absolute right-0 top-1/2 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:text-dark-green sm:flex">
                <x-app-icon name="chevron-right" class="size-4" />
            </button>
        </div>
    </div>
@endif
