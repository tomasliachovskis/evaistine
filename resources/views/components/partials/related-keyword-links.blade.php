{{-- Grid of links to other keyword pages ($links: label/href, optional
     matching_offers_count). Shared by the keyword page's "Susijusios akcijos"
     block and its no-offers empty state. --}}
<div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4">
    @foreach ($links as $related)
        <a href="{{ $related['href'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-800 transition-colors hover:border-green/40">
            <span class="min-w-0 leading-snug">{{ $related['label'] }}</span>
            @if (!empty($related['matching_offers_count']))
                <span class="ml-auto shrink-0"><x-count-pill :count="$related['matching_offers_count']" color="gray" /></span>
            @endif
        </a>
    @endforeach
</div>
