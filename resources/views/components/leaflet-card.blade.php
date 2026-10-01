@props(['leaflet', 'showStoreName' => true])

@php
    $daysWord = fn ($n) => match (true) {
        $n === 1 => 'diena',
        $n % 10 >= 2 && $n % 10 <= 9 && !($n % 100 >= 11 && $n % 100 <= 19) => 'dienas',
        default => 'dienų',
    };
    $isExpired = $leaflet['status'] === 'expired';
    $href = $leaflet['view_url'] ?? "/leidinys/{$leaflet['store_slug']}";
    $days = $leaflet['days_remaining'] ?? null;
    $dateRange = !empty($leaflet['valid_from']) && !empty($leaflet['valid_to'])
        ? \App\Support\LithuanianDate::range(\Illuminate\Support\Carbon::parse($leaflet['valid_from']), \Illuminate\Support\Carbon::parse($leaflet['valid_to']))
        : null;
@endphp

{{-- Matches the mockup's .leaflet-card exactly: the whole card is one link
     (no separate CTA button inside), title/date/status only in the body.
     Same design as leaflets/hub.blade.php's (/leidinys/{store}) — kept
     identical on purpose so /leidiniai (all stores) and a single store's own
     hub page don't look like two different products. One addition here:
     a store-name line above the title — needed on this multi-store listing
     to say which store a card is even for (a real thumbnail photo doesn't
     self-label like the mockup's placeholder text does), unlike
     hub.blade.php's version where the whole page is already scoped to one
     store. --}}
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group flex flex-row gap-3 overflow-hidden rounded-[14px] border border-gray-200 bg-white shadow-sm transition-shadow sm:flex-col sm:gap-0 sm:hover:shadow-lg']) }}>
    <div class="relative block w-2/5 shrink-0 overflow-hidden bg-gray-50 sm:aspect-[6/5] sm:w-full">
        @if (!empty($leaflet['thumbnail_url'] ?? $leaflet['image_url'] ?? null))
            <img
                src="{{ $leaflet['thumbnail_url'] ?? $leaflet['image_url'] }}"
                alt="{{ $leaflet['title'] ?? $leaflet['store_name'] }}"
                loading="lazy"
                class="h-full w-full object-cover object-top transition-transform duration-300 sm:group-hover:scale-[1.03] {{ $isExpired ? 'grayscale' : '' }}"
            >
        @else
            <div class="flex h-full items-center justify-center px-2 text-center text-xs text-gray-500">{{ $leaflet['title'] ?? $leaflet['store_name'] }}</div>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-1.5 py-2.5 pr-3 sm:gap-1.5 sm:p-3">
        @if ($showStoreName)
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $leaflet['store_name'] }}</p>
        @endif
        <p class="{{ $showStoreName ? '-mt-1' : '' }} line-clamp-2 text-sm font-bold text-gray-900">{{ $leaflet['title'] ?? '' }}</p>
        @if ($dateRange)
            {{-- A consistent, real identifier for every card — some
                 leaflets carry a themed campaign name instead of a
                 sequential number (e.g. "Skonių dienos"), so a fixed
                 "Nr. X" can't be shown for all of them without
                 fabricating one; the validity date range is always
                 real and always available. --}}
            <p class="text-xs font-medium text-gray-500">{{ $dateRange }}</p>
        @endif
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $isExpired ? 'text-gray-400' : ($days !== null && $days <= 2 ? 'text-red-600' : 'text-dark-green') }}">
            <x-app-icon name="clock" class="size-3.5" />
            {{ $isExpired ? 'Nebegalioja' : ($days !== null ? "Galioja dar {$days} {$daysWord($days)}" : 'Galioja') }}
        </span>
    </div>
</a>
