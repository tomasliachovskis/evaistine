@props(['pages', 'ariaLabel' => 'Susiję pasiūlymai', 'title' => null])

{{-- Related keyword-page links (e.g. "Makaronai", "Aliejus"). Wrapped rows
     with a "Rodyti visas" button: the first 4 on phones (two even
     columns), 12 from sm. This
     used to be one sideways-scrolling row with arrows, which older readers
     didn't scroll; the chips also looked like the stat pills above them.
     Now green link text, with the count in brackets ("Dešrelės (100)") so
     the number reads as a count. --}}
@php
    $mobileLimit = 4;
    $desktopLimit = 12;
    $count = count($pages);
@endphp

@if ($count > 0)
    <div x-data="{ all: false }">
        @if ($title)
            <p class="mb-2 text-base font-semibold text-gray-700">{{ $title }}</p>
        @endif
        <nav aria-label="{{ $title ?? $ariaLabel }}" class="flex flex-wrap items-center gap-2">
            @foreach ($pages as $page)
                <a href="{{ $page['href'] }}"
                   @if ($loop->index >= $desktopLimit) x-show="all" x-cloak @elseif ($loop->index >= $mobileLimit) :class="all ? '' : 'max-sm:hidden!'" @endif
                   class="inline-flex min-h-12 w-[calc(50%-0.25rem)] items-center gap-1.5 rounded-xl bg-white px-3.5 py-1.5 text-base leading-snug sm:w-auto sm:py-0 font-semibold text-dark-green ring-1 ring-gray-200 transition-colors hover:bg-green/5 hover:ring-green/40">
                    @if (!empty($page['logo_slug']))
                        <x-store-logo :slug="$page['logo_slug']" :name="$page['title']" size="xs" />
                    @endif
                    <span>{{ $page['title'] }}@if (!empty($page['matching_offers_count'])) <span class="whitespace-nowrap font-normal tabular-nums text-gray-500">({{ number_format($page['matching_offers_count'], 0, ',', ' ') }})</span>@endif</span>
                </a>
            @endforeach
            @if ($count > $mobileLimit)
                <button type="button" x-show="!all" @click="all = true" class="{{ $count > $desktopLimit ? 'inline-flex' : 'inline-flex sm:hidden' }} min-h-12 items-center gap-1 rounded-xl px-3 text-base font-bold text-gray-900 underline underline-offset-4 hover:text-dark-green">
                    Rodyti visas ({{ $count }})
                    <x-app-icon name="chevron-down" class="size-5" />
                </button>
            @endif
        </nav>
    </div>
@endif
