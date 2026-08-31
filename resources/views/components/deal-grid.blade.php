@props(['deals', 'pagination' => null, 'basePath' => null, 'contextStoreSlug' => null])

{{-- Grid classes ported verbatim from discount/src/components/common/discounts-list.tsx's
     default (gridVariant="default") grid, which is what every listing page uses. --}}
@if (empty($deals))
    <p class="w-full text-center font-bold">Pagal Jūsų užklausą neradome nei vienos prekės</p>
@else
    <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 2xl:grid-cols-4">
        @foreach ($deals as $deal)
            <x-deal-card :deal="$deal" class="h-full" :context-store-slug="$contextStoreSlug" />
        @endforeach
    </div>

    @if ($pagination && ($pagination['last_page'] ?? 1) > 1)
        <nav class="mt-8 flex items-center justify-center gap-3 text-sm" aria-label="Puslapiavimas">
            @php
                $current = (int) $pagination['current_page'];
                $last = (int) $pagination['last_page'];
                $baseQuery = request()->except('page');
            @endphp
            @if ($current > 1)
                <a href="{{ $basePath }}?{{ http_build_query(array_merge($baseQuery, ['page' => $current - 1])) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50">← Ankstesni</a>
            @endif
            <span class="px-2 text-gray-600">{{ $current }} / {{ $last }}</span>
            @if ($current < $last)
                <a href="{{ $basePath }}?{{ http_build_query(array_merge($baseQuery, ['page' => $current + 1])) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50">Kiti →</a>
            @endif
        </nav>
    @endif
@endif
