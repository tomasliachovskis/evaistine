@props(['pages', 'ariaLabel' => 'Susiję pasiūlymai'])

{{-- Related keyword-page links (e.g. "pieno gaminiai", "šokoladas") as a
     single horizontally-scrollable pill row — explicit product decision:
     one line, scrollable, instead of the previous wrap-to-2-rows +
     "Rodyti daugiau" toggle. --}}
@if (!empty($pages))
    <nav
        aria-label="{{ $ariaLabel }}"
        class="scroll-cards-x flex flex-nowrap items-center gap-2.5"
    >
        @foreach ($pages as $page)
            <a href="{{ $page['href'] }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-600 transition-colors hover:border-green/40">
                @if (!empty($page['logo_slug']))
                    <x-store-logo :slug="$page['logo_slug']" :name="$page['title']" size="xs" />
                @endif
                {{ $page['title'] }}
                @if (!empty($page['matching_offers_count']))
                    <x-count-pill :count="$page['matching_offers_count']" color="gray" />
                @endif
            </a>
        @endforeach
    </nav>
@endif
