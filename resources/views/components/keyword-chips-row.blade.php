@props(['pages', 'ariaLabel' => 'Susiję pasiūlymai'])

{{-- Related keyword-page links (e.g. "pieno gaminiai", "šokoladas") as a
     single scrollable pill row — same visual language as <x-store-nav-tabs>'s
     gray category pills, but a flat related-links list, not a tab switcher. --}}
@if (!empty($pages))
    <nav aria-label="{{ $ariaLabel }}" class="scroll-cards-x flex flex-nowrap items-center gap-2.5">
        @foreach ($pages as $page)
            <a href="{{ $page['href'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-base font-semibold text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                {{ $page['title'] }}
                @if (!empty($page['matching_offers_count']))
                    <x-count-pill :count="$page['matching_offers_count']" color="gray" />
                @endif
            </a>
        @endforeach
    </nav>
@endif
