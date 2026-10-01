@props(['leaflets' => []])

{{-- Homepage "Naujausi akcijų leidiniai" row, shared by "/" and /pradzia-beta.
     Leaflets — same frame + card language as the rest of the page
     (rounded-xl border, image on top, small store logo, minimal
     text, whole card as one link) instead of <x-leaflet-card>'s
     own heavier look (uppercase label, date range, full-width
     button) — that design is right for /leidiniai itself, but
     next to the comparison/deal cards here it read as a
     different product. Same grid as the deals section above, no
     carousel, for the same reason. --}}
@if (count($leaflets))
    <div {{ $attributes }}>
        <div class="section-heading-row">
            <h2 class="section-heading-lg">Naujausi akcijų leidiniai</h2>
            <a href="/leidiniai" class="section-link">
                Žiūrėti visus
                <x-app-icon name="chevron-right" class="size-4 opacity-80" />
            </a>
        </div>
        {{-- Two to a row on phones, a 4-col grid at sm+. --}}
        <div class="mt-4 flex flex-wrap gap-3 sm:grid sm:grid-cols-4">
            @foreach ($leaflets as $leaflet)
                @php
                    $isExpired = $leaflet['status'] === 'expired';
                    $days = $leaflet['days_remaining'] ?? null;
                    $href = $leaflet['view_url'] ?? "/leidinys/{$leaflet['store_slug']}";
                @endphp
                <a href="{{ $href }}" class="flex w-[calc(50%-0.375rem)] flex-col gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 hover:opacity-80 sm:w-auto">
                    <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-white">
                        @if (!empty($leaflet['thumbnail_url'] ?? $leaflet['image_url'] ?? null))
                            <img src="{{ $leaflet['thumbnail_url'] ?? $leaflet['image_url'] }}" alt="{{ $leaflet['title'] ?? $leaflet['store_name'] }}" loading="lazy" class="h-full w-full object-contain p-2 {{ $isExpired ? 'grayscale' : '' }}">
                        @else
                            <div class="flex h-full items-center justify-center">
                                <x-store-logo :slug="$leaflet['store_slug']" :name="$leaflet['store_name']" size="md" />
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-1.5">
                        <x-store-logo :slug="$leaflet['store_slug']" :name="$leaflet['store_name']" size="xs" />
                        <span class="text-sm font-semibold text-gray-500">{{ $leaflet['store_name'] }}</span>
                    </div>
                    <p class="line-clamp-2 text-[0.9em] font-semibold leading-tight text-gray-900">{{ $leaflet['title'] ?? $leaflet['store_name'] }}</p>
                    <span @class([
                        'text-sm font-semibold',
                        'text-gray-400' => $isExpired,
                        'text-red-600' => !$isExpired && $days !== null && $days <= 2,
                        'text-dark-green' => !$isExpired && ($days === null || $days > 2),
                    ])>
                        {{ $isExpired ? 'Nebegalioja' : ($days !== null ? "Galioja dar {$days} d." : 'Galioja') }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endif
