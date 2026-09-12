@props(['pages', 'ariaLabel' => 'Susiję pasiūlymai'])

{{-- Related keyword-page links (e.g. "pieno gaminiai", "šokoladas") as a
     wrapping pill row — never horizontal-scroll: this list grows with however
     many related pages a category has, and hiding items behind a swipe a
     40+ user might not discover is exactly the pattern ruled out everywhere
     else on these pages (nav-tabs-row, chip-row, switch-row all wrap too). --}}
@if (!empty($pages))
    <nav aria-label="{{ $ariaLabel }}" class="flex flex-wrap items-center gap-2.5">
        @foreach ($pages as $page)
            <a href="{{ $page['href'] }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-600 transition-colors hover:border-green/40">
                {{ $page['title'] }}
                @if (!empty($page['matching_offers_count']))
                    <x-count-pill :count="$page['matching_offers_count']" color="gray" />
                @endif
            </a>
        @endforeach
    </nav>
@endif
